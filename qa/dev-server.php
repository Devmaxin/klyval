<?php
// Local dev server router for `php -S SERVER qa/dev-server.php`: serve static files for real paths, otherwise the front controller.
// qa/-only; the Vercel container uses the Caddyfile instead.
$root = dirname(__DIR__);
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if (preg_match('#^/assets/#', $path) || $path === '/favicon.svg') {
    $file = $root . '/public' . $path;
    if (is_file($file)) { return false; }   // built-in server serves it directly
}
require $root . '/public/index.php';
