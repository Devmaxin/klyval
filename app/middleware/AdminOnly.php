<?php
/** Settings, hard deletes and other sensitive actions: administrators only. Editors get a 403. */
final class AdminOnly {
    public static function handle(): void {
        if (($_SESSION['admin_role'] ?? '') !== 'admin') {
            http_response_code(403);
            View::render('admin/forbidden', ['meta' => ['title' => 'Not allowed', 'robots' => 'noindex']], 'layouts/admin');
            exit;
        }
    }
}
