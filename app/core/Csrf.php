<?php
final class Csrf {
    public static function token(): string {
        return $_SESSION['_csrf'] ??= bin2hex(random_bytes(32));
    }
    /** Route middleware for every state-changing request. */
    public static function handle(): void {
        if (($_SERVER['CONTENT_LENGTH'] ?? 0) > 0 && !$_POST && !$_FILES) { $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
            if ($path === '/admin/media/upload') { $_SESSION['flash'] = ['err', 'That file is larger than the server allows. Use an image under ' . media_max_mb() . ' MB.']; redirect('/admin/media'); }
            http_response_code(413);
            View::render('pages/413', ['meta' => ['title' => 'Too large', 'robots' => 'noindex']]);
            exit; }
        $sent = $_POST['_csrf'] ?? '';
        if (!is_string($sent) || !hash_equals(self::token(), $sent)) {
            http_response_code(419);
            exit('Session expired. Go back, refresh the page and try again.');
        }
    }
}
