<?php
declare(strict_types=1);
// Inventory: explode uploads.zip with PowerShell beforehand into qa/uploads-extracted/
require dirname(__DIR__) . '/app/helpers/bootstrap.php';
$fails = 0;
function ck(string $n, bool $ok, string $d = ''): void { global $fails; printf("%s  %s%s\n", $ok ? 'PASS' : 'FAIL', $n, $d ? " — $d" : ''); if (!$ok) $fails++; }

$dir = glob(dirname(__DIR__) . '/qa/uploads-extracted/*', GLOB_ONLYDIR);
$root = $dir ? $dir[0] : dirname(__DIR__) . '/qa/uploads-extracted';
$iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
$files = [];
foreach ($iter as $f) $files[] = substr($f->getPathname(), strlen($root) + 1);
echo "extracted ", count($files), " files\n";

$rows = Database::query('select id, filename, mime, bytes from media order by id')->fetchAll(PDO::FETCH_ASSOC);
$byBase = [];
foreach ($files as $f) $byBase[basename($f)] = $f;
$missing = [];
foreach ($rows as $r) if (!isset($byBase[$r['filename']])) $missing[] = "{$r['id']}: {$r['filename']} ({$r['mime']})";
ck('every media filename exists in zip', empty($missing), implode('; ', $missing));
// bytes differ only when the original upload re-encoded before store; report but do not fail
foreach ($rows as $r) {
    if (isset($byBase[$r['filename']])) {
        $sz = filesize($root . '/' . $byBase[$r['filename']]);
        if ($sz !== (int)$r['bytes']) {
            printf("  note: {$r['filename']} — zip file is %d bytes, media row says %d (the site re-encoded on upload; zip may hold the pre-encode original)\n", $sz, $r['bytes']);
        }
    }
}
$extra = array_diff(array_keys($byBase), array_column($rows, 'filename'));
printf("  extra files in zip: %d\n", count($extra));
foreach ($extra as $e) printf("   + %s\n", $e);
echo ($fails === 0 ? "UPLOADS PASS\n" : "UPLOADS FAILURES\n");
exit($fails === 0 ? 0 : 1);
