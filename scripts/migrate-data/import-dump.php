<?php
// Klyval: import a MySQL dump of the legacy InfinityFree database into the Postgres/Supabase schema.
//
// Usage (CLI only):
//   php scripts/migrate-data/import-dump.php <dump.sql> [options]
// Options:
//   --truncate-first       empty matching Postgres tables before import (default: refuse if data exists)
//   --source-tz=UTC        timezone the legacy datetimes were written in (e.g. UTC, +05:30, Asia/Kolkata).
//                          Default UTC. Get the truth from the legacy box yourself before importing:
//                            SELECT @@global.time_zone, @@session.time_zone, NOW(), UTC_TIMESTAMP();
//   --dry-run              parse and validate only; print what would be imported
//   --exclude=table[,tbl]  extra tables to leave out (default exclusions: sessions, login_attempts)
//
// Read-only with respect to MySQL: this script never connects to the legacy database.
// The only input is a plain-text SQL dump (mysqldump or phpMyAdmin export).
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
require dirname(__DIR__, 2) . '/app/helpers/bootstrap.php';

// Manual argv parsing: PHP's getopt() bails out at the first non-option argument, which would
// quietly ignore any option written after the dump path (e.g. `import-dump.php dump.sql --dry-run`).
$opts = []; $args = [];
foreach (array_slice($argv, 1) as $a) {
    if (str_starts_with($a, '--')) {
        $kv = substr($a, 2);
        if (str_contains($kv, '=')) { [$k, $v] = explode('=', $kv, 2); $opts[$k] = $v; }
        else $opts[$kv] = true;
    } else $args[] = $a;
}
if (isset($opts['help']) || empty($args)) {
    fwrite(STDERR, "Usage: php scripts/migrate-data/import-dump.php <dump.sql> [--truncate-first] [--source-tz=UTC|+05:30] [--dry-run] [--exclude=t1,t2]\n");
    exit(1);
}
$file      = $args[0];
$dry       = isset($opts['dry-run']);
$truncate  = isset($opts['truncate-first']);
$sourceTz  = new DateTimeZone(is_string($opts['source-tz'] ?? null) ? $opts['source-tz'] : 'UTC');
$excludes  = array_flip(array_filter(array_merge(
    ['sessions', 'login_attempts'],
    preg_split('/\s*,\s*/', (string)($opts['exclude'] ?? '')) ?: [])));

if (!is_file($file)) exit("dump file not found: $file\n");

// Target universe: tables that exist in both the legacy MySQL migrations and the Postgres schema.
$pgTables = array_column(Database::query("select tablename from pg_tables where schemaname = 'public'")->fetchAll(), 'tablename');

$legacyOrder = [];
foreach (glob(dirname(__DIR__, 2) . '/database/migrations/*.sql') as $mf) {
    $sql = (string)file_get_contents($mf);
    if (!preg_match_all('/CREATE TABLE (?:IF NOT EXISTS )?`?(\w+)`?\s*\((.*?)(?:\)\s*ENGINE|;)/is', $sql, $m, PREG_SET_ORDER)) continue;
    foreach ($m as $t) {
        $cols = [];
        foreach (preg_split('/\r?\n/', $t[2]) as $line) {
            $line = trim($line);
            if ($line === '' || preg_match('/^(UNIQUE|KEY|PRIMARY|CONSTRAINT|FULLTEXT|SPATIAL)\b/i', $line)) continue;
            if (preg_match('/^`?(\w+)`?\s+[A-Z]/i', $line, $c) && !in_array($c[1], $cols, true)) $cols[] = $c[1];
        }
        if ($cols) $legacyOrder[$t[1]] = $cols;
    }
}
$importable = array_diff(array_intersect(array_keys($legacyOrder), $pgTables), array_keys($excludes));
printf("Legacy tables: %s\nImporting into %d tables. Excluded by policy: %s\n",
    implode(', ', array_keys($legacyOrder)),
    count($importable),
    implode(', ', array_merge(array_diff(array_keys($legacyOrder), $pgTables),
                              array_intersect(array_keys($excludes), array_keys($legacyOrder)))));

// ---------- statement tokenizer: split the dump on ';' outside quotes ----------
function extractInserts(string $src): array {
    $stmts = []; $i = 0; $n = strlen($src); $start = 0; $q = null;
    while ($i < $n) {
        $ch = $src[$i];
        if ($q !== null) {
            if ($q === "'" || $q === '"') {
                if ($ch === '\\') { $i += 2; continue; }                       // MySQL backslash escape
                if ($ch === $q && ($src[$i + 1] ?? '') === $q) { $i += 2; continue; } // '' doubling
            }
            if ($ch === $q) $q = null;
        } elseif ($ch === "'" || $ch === '"' || $ch === '`') $q = $ch;
        elseif ($ch === ';') { $stmts[] = substr($src, $start, $i - $start); $start = $i + 1; }
        $i++;
    }
    $stmts[] = substr($src, $start);
    // drop pure `--` comment lines (mysqldump/phpMyAdmin add banner comments around statements)
    return array_values(array_filter(array_map(
        function ($s) {
            $s = preg_replace('/^\s*--.*\r?\n?/m', '', $s);
            return strlen(trim((string)$s)) ? trim((string)$s) : '';
        }, $stmts), 'strlen'));
}

// ---------- parse: INSERT INTO `t` (cols)? VALUES (...),(...),... ----------
function parseColumnList(string $list): array {
    return array_map(fn($c) => trim(trim(trim($c), '`')), array_filter(explode(',', $list), 'trim'));
}

function parseInsert(string $stmt): ?array {
    if (!preg_match('/^(?:INSERT|REPLACE)\s+(?:IGNORE\s+)?INTO\s+(`?\w+`?)\s*(\([^\)]+\))?\s*VALUES\s/is', trim($stmt), $m)) return null;
    $table = trim($m[1], '`');
    $cols  = isset($m[2]) ? parseColumnList(trim($m[2], ' ()')) : [];
    $rest  = trim(substr(trim($stmt), strlen($m[0]) - 1));
    $tuples = []; $body = ''; $qlvl = 0; // paren depth handled manually
    $depth = 0; $q = null; $i = 0; $n = strlen($rest);
    while ($i < $n) {
        $ch = $rest[$i];
        if ($q !== null) {
            if ($ch === '\\') { $body .= substr($rest, $i, 2); $i += 2; continue; }
            if ($ch === $q && ($rest[$i + 1] ?? '') === $q) { $body .= $q . $q; $i += 2; continue; }
            $body .= $ch;
            if ($ch === $q) $q = null;
        } elseif ($ch === "'" || $ch === '"' || $ch === '`') { $q = $ch; $body .= $ch; }
        elseif ($ch === '(') { $depth++; if ($depth === 1) { $body = ''; } else { $body .= $ch; } }
        elseif ($ch === ')') {
            $depth--;
            if ($depth === 0) { $tuples[] = $body; $body = ''; }
            else $body .= $ch;
        }
        elseif ($depth > 0) $body .= $ch;
        $i++;
    }
    return [$table, $cols, $tuples];
}

/** Split one tuple body `a,b,'c,','d\e'` into raw value tokens (quotes+escapes preserved). */
function splitTuple(string $s): array {
    $vals = []; $cur = ''; $q = null; $i = 0; $n = strlen($s);
    while ($i < $n) {
        $ch = $s[$i];
        if ($q !== null) {
            if ($ch === '\\') { $cur .= substr($s, $i, 2); $i += 2; continue; }
            if ($ch === $q && ($s[$i + 1] ?? '') === $q) { $cur .= $q . $q; $i += 2; continue; }
            $cur .= $ch;
            if ($ch === $q) $q = null;
        } elseif ($ch === "'" || $ch === '"' || $ch === '`') { $q = $ch; $cur .= $ch; }
        elseif ($ch === ',') { $vals[] = trim($cur); $cur = ''; }
        else $cur .= $ch;
        $i++;
    }
    $vals[] = trim($cur);
    return $vals;
}

/** Unescape one MySQL scalar token: NULL, number, or single-quoted string. */
function unescape(?string $tok): ?string {
    if ($tok === null) return null;
    $tok = trim($tok);
    if (strcasecmp($tok, 'NULL') === 0) return null;
    if (!str_starts_with($tok, "'")) return $tok;                       // numbers/barewords pass through
    $body = substr($tok, 1, -1);
    $out = ''; $i = 0; $n = strlen($body);
    while ($i < $n) {
        if ($body[$i] === '\\' && $i + 1 < $n) { $out .= stripcslashes($body[$i] . $body[$i + 1]); $i += 2; continue; }
        if ($body[$i] === "'" && ($body[$i + 1] ?? '') === "'") { $out .= "'"; $i += 2; continue; } // '' => '
        $out .= $body[$i]; $i++;
    }
    return $out;
}

/** datetime columns: *_at, *_date, *_until, plain `date`; converted from naive source tz to UTC. */
function isDatetimeCol(string $col): bool { return preg_match('/(_at|_date|_until)$|^(date)$/i', $col) === 1; }

function convertDatetime(?string $v, DateTimeZone $sourceTz): ?string {
    if ($v === null || preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}(:\d{2})?)?$/', $v) !== 1) return $v; // pass exotic values through
    if (strlen($v) === 10) $v .= ' 00:00:00';
    $dt = new DateTime($v, $sourceTz);          // legacy DATETIME is naive; interpret in the source tz
    $dt->setTimezone(new DateTimeZone('UTC'));  // and re-emit as UTC (matches the app's APP_TZ=UTC policy)
    return $dt->format('Y-m-d\TH:i:sP');
}

// ---------- build import plan ----------
$src  = (string)file_get_contents($file);
$plan = []; $skipped = [];
foreach (extractInserts($src) as $st) {
    if (preg_match('/^--/', $st) || !preg_match('/^INSERT|REPLACE/mi', $st)) continue;
    $p = parseInsert($st);
    if (!$p) continue;
    [$table, $cols, $tuples] = $p;
    if (!in_array($table, $importable, true)) {
        foreach ($tuples as $tp) $skipped[$table] = ($skipped[$table] ?? 0) + 1;
        continue;
    }
    if (!$cols) {
        if (empty($legacyOrder[$table])) exit("no column list for `$table` and no legacy CREATE TABLE found — cannot infer column order\n");
        $cols = $legacyOrder[$table];
    }
    foreach ($tuples as $ti => $tp) {
        $vals = splitTuple($tp);
        if (count($vals) !== count($cols)) {
            exit(sprintf("tuple %d for `$table`: %d values for %d columns — aborting. (Dump with --complete-insert or with column lists to make this robust.)\n", $ti + 1, count($vals), count($cols)));
        }
        $plan[$table][] = array_combine($cols, array_map(fn($v) => unescape($v), $vals));
    }
}

$totalRows = array_sum(array_map('count', $plan));
foreach ($plan as $t => $rows) printf("  %-25s %5d rows\n", $t, count($rows));
if ($skipped) foreach ($skipped as $t => $c) printf("  skipped %-21s %5d rows\n", $t, $c);
printf("Parsed %d rows / %d tables.\n", $totalRows, count($plan));
if ($dry || !$totalRows) exit($dry ? "dry run complete — nothing written\n" : (empty($plan) ? "no matching INSERTs found in dump\n" : ""));

// ---------- safety: refuse populated targets unless --truncate-first ----------
foreach ($plan as $t => $rows) {
    $c = (int)Database::query("select count(*) from \"$t\"")->fetchColumn();
    if ($c > 0) {
        if ($truncate) printf("- %-25s has %d rows; truncating\n", $t, $c);
        else exit(sprintf("table \"%s\" already has %d rows — use --truncate-first or clean it up first\n", $t, $c));
    }
}

// ---------- import, FK-safe order, then fix identities ----------
$order = ['admin_users', 'media', 'technologies', 'skills', 'services', 'service_features', 'team_members',
          'homepage_settings', 'site_settings', 'homepage_stats', 'social_links', 'site_media',
          'projects', 'project_slug_history', 'project_media', 'project_technologies', 'project_services', 'project_team',
          'team_slug_history', 'team_socials', 'team_skills',
          'articles', 'article_slug_history', 'contact_submissions'];
$targets = array_merge(array_intersect($order, array_keys($plan)), array_diff(array_keys($plan), $order));

// Legacy MySQL column names divergent from the Postgres schema (source of truth: supabase/migrations).
$renames = ['alt' => 'alt_text'];

// Phase 1: truncate everything being imported (children first) so FKs don't block parenting deletes.
if ($truncate) {
    Database::transaction(function () use ($targets) {
        foreach (array_reverse($targets) as $t) {
            Database::query("delete from \"$t\"");
        }
        printf("%d tables truncated\n", count($targets));
    });
}

// Phase 2: import each table's rows (parents first), then fix identity sequences.
foreach ($targets as $t) {
    Database::transaction(function () use ($t, $plan, $sourceTz, $renames) {
        // actual columns in the target table; drop anything the Postgres schema does not have (with a warning)
        $pgCols = array_column(Database::query(
            "select column_name from information_schema.columns where table_schema='public' and table_name=?", [$t]
        )->fetchAll(), 'column_name');
        if (empty($pgCols)) { printf("  ! table \"%s\" not found in public schema — skipping\n", $t); return; }
        // Resolve the column mapping once from the first row and warn about anything unsupported.
        $colMap = []; $dropped = [];
        foreach (($plan[$t][0] ?? []) ? array_keys($plan[$t][0]) : [] as $col) {
            $tgt = $renames[$col] ?? $col;
            if (in_array($tgt, $pgCols, true)) $colMap[$col] = $tgt;
            else $dropped[] = $col;
        }
        if ($dropped) printf("  ! dropping unsupported columns for \"%s\": %s\n", $t, implode(', ', $dropped));
        foreach ($plan[$t] as $row) {
            $mapped = [];
            foreach (array_keys($colMap) as $col) {
                $mapped[$colMap[$col]] = isDatetimeCol($col) ? convertDatetime($row[$col], $sourceTz) : $row[$col];
            }
            $cols    = array_keys($mapped);
            $ph      = implode(',', array_fill(0, count($cols), '?'));
            $collist = implode(',', array_map(fn($c) => '"' . $c . '"', $cols));
            Database::query("insert into \"$t\" ($collist) values ($ph)", array_values($mapped));
        }
        // bump identity sequence past the highest id we just inserted
        $hasId = Database::query("select count(*) from information_schema.columns where table_schema='public' and table_name=? and column_name='id'", [$t])->fetchColumn();
        if ((int)$hasId === 1) {
            try {
                Database::query("select setval(pg_get_serial_sequence(?, 'id'), (select coalesce(max(id),0) from \"$t\"), true)", [$t]);
            } catch (Throwable) { /* table without identity/serial */ }
        }
        printf("  imported %-24s %5d rows\n", $t, count($plan[$t]));
    });
}

echo "import complete\n";
