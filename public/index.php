<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/helpers/bootstrap.php';

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$isAdmin = $path === '/admin' || str_starts_with($path, '/admin/');
header('Content-Security-Policy: ' . Csp::build($isAdmin));

// Maintenance mode: public visitors see a 503. Admin (and signed-in admins) are unaffected.
if (!$isAdmin && Settings::get('maintenance_mode') === '1' && !Auth::check()) {
    http_response_code(503); header('Retry-After: 3600');
    View::render('pages/maintenance', ['meta' => ['title' => 'Back soon — Klyval', 'robots' => 'noindex'], 'message' => Settings::get('maintenance_message', "We're making some changes. Back shortly.")]);
    exit;
}

$router = new Router();
require ROOT . '/routes/web.php';
$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'] ?? '/');
