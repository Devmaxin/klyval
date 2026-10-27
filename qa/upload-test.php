<?php
// Usage (scratch DB + php-gd): php qa/upload-test.php
// Exercises MediaService::process() with real fixtures: valid/invalid types, polyglots, oversize, deletion and reference protection.
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
require dirname(__DIR__) . '/app/helpers/bootstrap.php';
$pass = 0; $fail = 0; $made = [];
function t(string $name, bool $ok): void { global $pass, $fail; $ok ? $pass++ : $fail++; echo ($ok ? '  PASS  ' : '  FAIL  ') . $name . "\n"; }
function tmp(string $bytes): string { $p = tempnam(sys_get_temp_dir(), 'kv'); file_put_contents($p, $bytes); return $p; }
function img(string $type, int $w = 64, int $h = 48, bool $alpha = false): string {
    $im = imagecreatetruecolor($w, $h); imagefilledrectangle($im, 0, 0, $w, $h, imagecolorallocate($im, 23, 105, 255));
    if ($alpha) { imagealphablending($im, false); imagesavealpha($im, true); imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 80)); }
    ob_start(); match ($type) { 'jpg' => imagejpeg($im), 'png' => imagepng($im), 'webp' => imagewebp($im), 'gif' => imagegif($im) }; $b = ob_get_clean(); imagedestroy($im); return $b;
}
function ok(array $r) { global $made; if (isset($r['id'])) $made[] = $r['id']; return isset($r['id']); }
echo "Valid images\n";
foreach (['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'] as $ext => $mime) {
    $r = MediaService::process(tmp(img($ext, 64, 48, $ext !== 'jpg')), "pic.$ext", 'alt', null);
    t("$ext accepted", ok($r));
    if (isset($r['id'])) { $m = MediaService::find($r['id']);
        t("$ext stored with stored mime $mime", $m['mime'] === $mime);
        t("$ext filename is random server-generated", (bool)preg_match('/^[a-f0-9]{32}\.' . $ext . '$/', $m['filename']));
        t("$ext file exists on disk", is_file(MediaService::dir() . '/' . $m['filename'])); }
}
echo "Rejected inputs\n";
$cases = [
    'PHP source named .jpg' => "<?php echo 'x';", 'plain text' => 'hello world', 'GIF' => img('gif'), 'SVG' => '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
    'truncated JPEG' => substr(img('jpg', 400, 300), 0, 60), 'empty file' => '',
];
foreach ($cases as $label => $bytes) t("rejects $label", !ok(MediaService::process(tmp($bytes), 'evil.jpg', '', null)));
t('rejects too-small image (8x8)', !ok(MediaService::process(tmp(img('png', 8, 8)), 'a.png', '', null)));
t('rejects side > 6000 px', !ok(MediaService::process(tmp(img('png', 6100, 20)), 'wide.png', '', null)));
t('rejects > 5 MB file', !ok(MediaService::process(tmp(img('png') . str_repeat("\0", 5 * 1048576 + 10)), 'big.png', '', null)));
echo "Polyglot / metadata stripping\n";
$poly = img('jpg') . "\n<?php echo 'pwned'; ?>";
$r = MediaService::process(tmp($poly), 'poly.jpg', '', null);
t('polyglot JPEG is re-encoded (not rejected, not copied)', ok($r));
if (isset($r['id'])) { $m = MediaService::find($r['id']); t('payload does not survive re-encoding', !str_contains((string)file_get_contents(MediaService::dir() . '/' . $m['filename']), '<?php')); }
echo "Large image is scaled down\n";
$r = MediaService::process(tmp(img('jpg', 3000, 2000)), 'large.jpg', '', null);
t('3000x2000 accepted', ok($r));
if (isset($r['id'])) { $m = MediaService::find($r['id']); t('stored longest side <= 2400', max($m['width'], $m['height']) <= 2400); }
echo "Traversal in the original name\n";
$r = MediaService::process(tmp(img('png')), '../../../etc/passwd.png', '', null);
t('accepted but never used as a path', ok($r));
if (isset($r['id'])) { $m = MediaService::find($r['id']); t('original name is display-only and has no slashes', !str_contains((string)$m['original_name'], '/') && !str_contains((string)$m['filename'], '..')); }

echo "Deletion and reference protection\n";
$r = MediaService::process(tmp(img('png', 80, 60)), 'cover.png', '', null); $id = $r['id']; $made[] = $id; $file = MediaService::dir() . '/' . MediaService::find($id)['filename'];
Database::query("INSERT INTO projects (slug, name, kind, status, is_placeholder) VALUES ('qa-upload-test', 'QA upload test', 'client', 'draft', 1)");
$pid = (int)Database::pdo()->lastInsertId();
Database::query('UPDATE projects SET cover_media_id = ? WHERE id = ?', [$id, $pid]);
t('delete is refused while used as a cover', MediaService::delete($id) !== null);
t('file is still on disk after refused delete', is_file($file) && MediaService::find($id) !== null);
Database::query('UPDATE projects SET cover_media_id = NULL WHERE id = ?', [$pid]);
Database::query('INSERT INTO project_media (project_id, media_id) VALUES (?, ?)', [$pid, $id]);
t('gallery-only reference does not block delete', MediaService::delete($id) === null);
t('record removed', MediaService::find($id) === null);
t('gallery link cleaned up', (int)Database::query('SELECT COUNT(*) FROM project_media WHERE media_id = ?', [$id])->fetchColumn() === 0);
t('file removed from disk once unreferenced', !is_file($file));
Database::query('DELETE FROM projects WHERE id = ?', [$pid]);
foreach ($made as $mid) { if (MediaService::find($mid)) MediaService::delete($mid); }
t('no stray files left in uploads', MediaService::strayFiles() === []);
echo "\nPassed $pass, failed $fail\n"; exit($fail ? 1 : 0);
