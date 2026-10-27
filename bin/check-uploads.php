<?php
// Usage: php bin/check-uploads.php https://your-site.example
// Proves PHP does NOT execute inside /uploads/ on the live server (Apache or Nginx): it drops a harmless probe script there,
// requests it over HTTP, and fails if the output was produced by executing PHP. Run after every server change and deploy.
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
require dirname(__DIR__) . '/app/helpers/bootstrap.php';
$base = rtrim((string)($argv[1] ?? ''), '/');
if (!preg_match('#^https?://#', $base)) exit("Usage: php bin/check-uploads.php https://your-site.example\n");
$dir = MediaService::dir();
if (!is_dir($dir) && !mkdir($dir, 0755, true)) exit("Cannot create $dir\n");
$failed = false;
foreach (['php', 'phtml', 'phar'] as $ext) {
    $name = '__probe_' . bin2hex(random_bytes(4)) . '.' . $ext;
    file_put_contents("$dir/$name", "<?php echo 'KLYVAL-' . 'EXECUTED-' . (40 + 2);");   // raw source never contains the string EXECUTED-42
    $ctx = stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 10, 'follow_location' => 0]]);
    $body = @file_get_contents("$base/uploads/$name", false, $ctx);
    $status = $http_response_header[0] ?? 'no response';
    @unlink("$dir/$name");
    if (str_contains((string)$body, 'EXECUTED-42')) { echo "FAIL  .$ext executes inside /uploads/  ($status)\n"; $failed = true; }
    else echo "PASS  .$ext not executed  ($status)\n";
}
$stray = MediaService::strayFiles();
if ($stray) { echo 'FAIL  unexpected files in uploads: ' . implode(', ', $stray) . "\n"; $failed = true; } else echo "PASS  no unexpected files in uploads\n";
exit($failed ? 2 : 0);
