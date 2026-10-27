<?php
final class AuthController {
    private const DUMMY_HASH = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

    public function showLogin(): void {
        if (Auth::check()) redirect('/admin');
        $this->form(null);
    }

    public function login(): void {
        $rawEmail = mb_substr(trim((string)($_POST['email'] ?? '')), 0, 190);
        $email = filter_var($rawEmail, FILTER_VALIDATE_EMAIL) ?: '';
        $pass = (string)($_POST['password'] ?? '');

        if (LoginThrottle::blocked($rawEmail)) {
            http_response_code(429); header('Retry-After: ' . LoginThrottle::WINDOW);
            $this->form('Too many attempts. Try again in 15 minutes.');
            return;
        }
        $u = $email ? Database::query('SELECT id, name, role, password_hash FROM admin_users WHERE email = ? AND is_active = 1', [$email])->fetch() : false;
        // Always hash-check so timing doesn't reveal whether the email exists.
        $ok = password_verify($pass, $u['password_hash'] ?? self::DUMMY_HASH) && $u;
        if ($ok) {
            LoginThrottle::success($rawEmail);
            Database::query('UPDATE admin_users SET last_login_at = NOW() WHERE id = ?', [$u['id']]);
            Auth::login((int)$u['id'], (string)$u['name'], (string)$u['role']);
            redirect('/admin');
        }
        LoginThrottle::fail($rawEmail);
        http_response_code(401);
        $this->form('Email or password is incorrect.');
    }

    public function logout(): void { Auth::logout(); redirect('/admin/login'); }

    private function form(?string $error): void {
        View::render('admin/login', ['error' => $error, 'meta' => ['title' => 'Sign in — Klyval admin', 'robots' => 'noindex']], 'layouts/admin');
    }
}
