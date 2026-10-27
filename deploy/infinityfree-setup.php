<?php
declare(strict_types=1);
/**
 * TEMPORARY installer for hosts with no SSH (InfinityFree). Copy this file to  htdocs/setup.php , open it once, then DELETE it.
 * It applies the database migrations and creates the FIRST administrator. It refuses to run without a secret SETUP_KEY in .env
 * and refuses to create an admin if one already exists, so leaving it behind by mistake cannot be used to take over the site.
 */
require __DIR__ . '/app/helpers/bootstrap.php';
header('Content-Type: text/html; charset=utf-8'); header('X-Frame-Options: DENY'); header('Cache-Control: no-store'); header('X-Robots-Tag: noindex');
$page = function (string $body): void { echo '<!doctype html><meta charset="utf-8"><meta name="robots" content="noindex"><title>Klyval setup</title><body style="font:16px/1.5 system-ui;max-width:34rem;margin:3rem auto;padding:0 1rem">' . $body; exit; };
$key = (string)Env::get('SETUP_KEY', '');
if (strlen($key) < 24) $page('<h1>Setup is locked</h1><p>Add a random <code>SETUP_KEY</code> of at least 24 characters to <code>.env</code>, then reload this page.</p>');

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::handle();   // session-bound token, same as the rest of the app
    if (!hash_equals($key, (string)($_POST['key'] ?? ''))) { sleep(2); http_response_code(403); $msg = '<p style="color:#b42318">The setup key is wrong.</p>'; }
    else {
        try {
            $out = Migrator::run();
            $name = trim((string)($_POST['name'] ?? '')); $email = trim((string)($_POST['email'] ?? '')); $pw = (string)($_POST['password'] ?? '');
            if (Database::query('SELECT COUNT(*) FROM admin_users')->fetchColumn() > 0) $out[] = 'An administrator already exists, so no new one was created.';
            elseif ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pw) < 12) { $msg = '<p style="color:#b42318">Enter a name, a valid email and a password of at least 12 characters.</p>'; }
            else { Database::query('INSERT INTO admin_users (name, email, password_hash, role) VALUES (?,?,?,?)', [mb_substr($name, 0, 120), $email, password_hash($pw, PASSWORD_DEFAULT), 'admin']); $out[] = 'Administrator created.'; }
            if ($msg === '') {
                $gone = @unlink(__FILE__);
                $page('<h1>Done</h1><ul><li>' . implode('</li><li>', array_map('htmlspecialchars', $out)) . '</li></ul><p><strong>Now:</strong> ' . ($gone ? 'this file deleted itself.' : 'delete <code>setup.php</code> from htdocs yourself.') . ' Then remove <code>SETUP_KEY</code> from <code>.env</code> and sign in at <a href="/admin">/admin</a>.</p>');
            }
        } catch (Throwable $e) { error_log('setup: ' . $e->getMessage()); http_response_code(500); $msg = '<p style="color:#b42318">Setup failed. Check the database values in .env, then try again. Details are in storage/logs/php.log.</p>'; }
    }
}
$page('<h1>Klyval setup</h1>' . $msg . '<form method="post"><input type="hidden" name="_csrf" value="' . htmlspecialchars(Csrf::token()) . '">
<p><label>Setup key (from .env)<br><input name="key" type="password" required style="width:100%"></label></p>
<p><label>Admin name<br><input name="name" required style="width:100%"></label></p>
<p><label>Admin email<br><input name="email" type="email" required style="width:100%"></label></p>
<p><label>Admin password (12+ characters)<br><input name="password" type="password" minlength="12" required style="width:100%"></label></p>
<button type="submit">Run setup</button></form>');
