<?php
// One-shot: upload every file in qa/uploads-extracted/ to the Supabase `media` bucket.
// Env needed: SUPABASE_URL + SUPABASE_SERVICE_ROLE (dashboard → Settings → API).
// POSTs each file as raw bytes to /upload/v1/file/{bucket}/{key}; echoes per-file result.
require dirname(__DIR__) . '/app/helpers/bootstrap.php';

$base = rtrim((string)Env::get('SUPABASE_URL', ''), '/');
$svc  = (string)Env::get('SUPABASE_SERVICE_ROLE', '');
if ($base === '' || $svc === '') exit("SUPABASE_URL and SUPABASE_SERVICE_ROLE are required.\n");

$srcDir = dirname(__DIR__) . '/qa/uploads-extracted';
$dir = glob($srcDir . '/*', GLOB_ONLYDIR);
$root = $dir ? $dir[0] : null;
$iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root ?? $srcDir, FilesystemIterator::SKIP_DOTS));
$fail = 0; $count = 0;
foreach ($iter as $f) {
    if (!$f->isFile()) continue;
    $key = preg_replace('#^.*uploads/#', '', $f->getPathname());
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f->getPathname());
    $ch = curl_init("$base/upload/v1/file/" . rawurlencode('media') . '/' . rawurlencode((string)$key));
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_POSTFIELDS => file_get_contents($f->getPathname()),
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer $svc",
            "apikey: $svc",
            "Content-Type: $mime",
            'x-upsert: true',
        ],
        CURLOPT_TIMEOUT => 120,
    ]);
    $res = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $body = substr((string)$res, (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE));
    curl_close($ch);
    if (!in_array($code, [200, 201], true)) {
        $fail++;
        printf("FAIL %-45s HTTP %d %s\n", $key, $code, mb_substr(trim((string)$body), 0, 120));
    } else {
        $count++;
        printf("  ok  %s\n", $key);
    }
}
printf("%d uploaded, %d failed\n", $count, $fail);
exit($fail === 0 ? 0 : 1);
