<?php
declare(strict_types=1);
define('ROOT', dirname(__DIR__, 2));

spl_autoload_register(function (string $c): void {
    foreach (['app/core', 'app/controllers', 'app/models', 'app/middleware', 'app/services'] as $d) {
        $f = ROOT . "/$d/$c.php";
        if (is_file($f)) { require $f; return; }
    }
});

require __DIR__ . '/helpers.php';
require __DIR__ . '/forms.php';
Env::load(ROOT . '/.env');
date_default_timezone_set(Env::get('APP_TZ', 'UTC') ?: 'UTC');

if (Env::get('APP_DEBUG') === '1') { ini_set('display_errors', '1'); error_reporting(E_ALL); }
else { ini_set('display_errors', '0'); ini_set('log_errors', '1'); ini_set('error_log', ROOT . '/storage/logs/php.log'); }

set_exception_handler(function (Throwable $e): void {
    error_log((string)$e);
    if (PHP_SAPI === 'cli') { fwrite(STDERR, $e . "\n"); return; }
    if (!headers_sent()) http_response_code(500);
    while (ob_get_level()) ob_end_clean();
    if (Env::get('APP_DEBUG') === '1') { echo '<pre>' . htmlspecialchars((string)$e) . '</pre>'; return; }
    try { View::render('pages/500', ['meta' => ['title' => 'Something went wrong', 'robots' => 'noindex']]); }
    catch (Throwable) { echo 'Something went wrong.'; }
});

Session::start();
