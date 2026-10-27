<?php
final class Auth {
    public static function check(): bool { return !empty($_SESSION['admin_id']); }
    public static function role(): string { return (string)($_SESSION['admin_role'] ?? ''); }

    public static function handle(): void {
        if (!self::check()) redirect('/admin/login');
        // Defense in depth: never trust the session copy of role/active alone.
        // A deactivated or demoted admin loses access on the very next request.
        $u = Database::query('SELECT is_active, role FROM admin_users WHERE id = ?', [(int)$_SESSION['admin_id']])->fetch();
        if (!$u || (int)$u['is_active'] !== 1) {
            self::logout();
            redirect('/admin/login');
        }
        if ((string)$u['role'] !== (string)($_SESSION['admin_role'] ?? '')) {
            $_SESSION['admin_role'] = (string)$u['role'];
        }
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
