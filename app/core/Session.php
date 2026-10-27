<?php
final class Session {
    private static ?DbSessionHandler $handler = null;
    public static function handler(): ?DbSessionHandler { return self::$handler; }

    public static function start(): void {
        if (PHP_SAPI === 'cli' || session_status() === PHP_SESSION_ACTIVE) return;
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_name('klyval_sid');
        $handler = new DbSessionHandler();
        session_set_save_handler($handler, true);
        self::$handler = $handler;
        $https = is_https();
        session_set_cookie_params([
            'lifetime' => 0, 'path' => '/', 'domain' => '',
            'secure' => $https, 'httponly' => true, 'samesite' => 'Lax',
        ]);
        session_start();
    }
}
