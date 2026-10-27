<?php
// Klyval: post-import verification for scripts/migrate-data/import-dump.php.
// Reads the imported Postgres database only; writes nothing.
// Usage: php scripts/migrate-data/verify-import.php --expect <counts.json>
// counts.json maps table names to expected row counts, e.g. {"projects": 2, "_total": 30}.
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
require dirname(__DIR__, 2) . '/app/helpers/bootstrap.php';

$expectFile = null;
$args = array_slice($argv, 1);
foreach ($args as $i => $a) {
    if ($a === '--expect') $expectFile = $args[$i + 1] ?? null;
}

$fails = 0;
function ck(string $n, bool $ok, string $detail = ''): void {
    global $fails;
    printf("%s  %s%s\n", $ok ? 'PASS' : 'FAIL', $n, $detail ? " — $detail" : '');
    if (!$ok) $fails++;
}

$tables = array_column(Database::query("select tablename from pg_tables where schemaname='public' order by tablename")->fetchAll(), 'tablename');

echo "== row counts ==\n";
$counts = [];
foreach ($tables as $t) {
    $counts[$t] = (int)Database::query("select count(*) from \"$t\"")->fetchColumn();
    printf("  %-25s %6d\n", $t, $counts[$t]);
}
if ($expectFile && is_file($expectFile)) {
    $expect = json_decode((string)file_get_contents($expectFile), true) ?: [];
    foreach ($expect as $t => $c) {
        if ($t === '_total') { ck('total rows', array_sum($counts) === (int)$c, sprintf('%d vs %d', array_sum($counts), $c)); continue; }
        ck("count $t", ($counts[$t] ?? -1) === (int)$c, sprintf('%d vs %d', $counts[$t] ?? 0, $c));
    }
}

echo "== integrity ==\n";
$orphans = [
    'media.uploaded_by'       => 'select count(*) from media m left join admin_users u on u.id = m.uploaded_by where m.uploaded_by is not null and u.id is null',
    'projects.cover_media_id' => 'select count(*) from projects p left join media m on m.id = p.cover_media_id where p.cover_media_id is not null and m.id is null',
    'project_technologies'    => 'select count(*) from project_technologies pt left join projects p on p.id = pt.project_id left join technologies t on t.id = pt.technology_id where p.id is null or t.id is null',
    'project_media'           => 'select count(*) from project_media pm left join projects p on p.id = pm.project_id left join media m on m.id = pm.media_id where p.id is null or m.id is null',
    'project_team'            => 'select count(*) from project_team pt left join projects p on p.id = pt.project_id left join team_members tm on tm.id = pt.member_id where p.id is null or tm.id is null',
    'project_services'        => 'select count(*) from project_services ps left join projects p on p.id = ps.project_id left join services s on s.id = ps.service_id where p.id is null or s.id is null',
    'team_socials.member_id'  => 'select count(*) from team_socials ts left join team_members tm on tm.id = ts.member_id where tm.id is null',
    'team_skills'             => 'select count(*) from team_skills ts left join team_members tm on tm.id = ts.member_id left join skills s on s.id = ts.skill_id where tm.id is null or s.id is null',
    'articles.author_id'      => 'select count(*) from articles a left join team_members tm on tm.id = a.author_id where a.author_id is not null and tm.id is null',
];
foreach ($orphans as $name => $sql) {
    $c = (int)Database::query($sql)->fetchColumn();
    ck("no orphans: $name", $c === 0, $c ? "$c orphan(s)" : '');
}

echo "== sequences ==\n";
foreach ($tables as $t) {
    if (($counts[$t] ?? 0) === 0) continue;
    $idType = Database::query("select data_type from information_schema.columns where table_schema='public' and table_name=? and column_name='id'", [$t])->fetchColumn();
    if (!$idType || !str_contains((string)$idType, 'int')) continue;   // no numeric id (site_media, site_settings) or text-keyed (sessions)
    $mx   = (int)Database::query("select coalesce(max(id),0) from \"$t\"")->fetchColumn();
    $seqN = (string)Database::query("select pg_get_serial_sequence('public.'||?, 'id')", [$t])->fetchColumn();
    if ($seqN === '') { ck("sequence for $t", false, 'no sequence'); continue; }
    $seqName = substr($seqN, strlen('public.'));
    $cur = (int)Database::query('select coalesce(last_value, 0) from pg_sequences where schemaname = ? and sequencename = ?', ['public', $seqName])->fetchColumn();
    ck("sequence >= max(id): $t", $cur >= $mx, sprintf('seq=%d max=%d', $cur, $mx));
}

echo "== app reads ==\n";
if (($counts['projects'] ?? 0) > 0 && ($counts['project_slug_history'] ?? 0) > 0) {
    $hist = Database::query('select h.old_slug from project_slug_history h join projects p on p.id = h.project_id limit 1')->fetchColumn();
    if ($hist) {
        $to = SiteContent::projectRedirect((string)$hist);
        ck('public 301 lookup works', $to !== null, "$hist -> " . ($to ?? 'null'));
    } else {
        ck('public 301 lookup works', false, 'no valid history row');
    }
}
if (($counts['articles'] ?? 0) > 0) {
    ck('SiteContent::articles non-empty', count(SiteContent::articles()) >= 1);
}

echo "== utf8/escape integrity ==\n";
$row = Database::query("select full_bio from team_members where slug = 'kly-avery'")->fetch();
if ($row) {
    ck('newline+quote escapes survived', str_contains($row['full_bio'], "\r\n") && str_contains($row['full_bio'], "'quotes'"));
    ck('backslash survived', str_contains($row['full_bio'], '\\'));
} else {
    ck('escape fixture row present', false, 'team_members kly-avery missing');
}

echo ($fails === 0 ? "\nVERIFY PASS\n" : "\nVERIFY FAILURES: $fails\n");
exit($fails === 0 ? 0 : 1);
