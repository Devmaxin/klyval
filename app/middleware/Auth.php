<?php
final class Auth {
    public static function check(): bool { return !empty($_SESSION['admin_id']); }
    public static function handle(): void {
        if (!self::check()) redirect('/admin/login');
    }
    public static function login(int $id, string $name, string $role): void {
        session_regenerate_id(true);
        $_SESSION['admin_id'] = $id;
        $_SESSION['admin_name'] = $name;
        $_SESSION['admin_role'] = $role;
    }
    public static function logout(): void {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => $p['path'], 'secure' => $p['secure'], 'httponly' => true, 'samesite' => 'Lax']);
        }
        session_destroy();
    }
}
