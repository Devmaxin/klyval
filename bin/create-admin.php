<?php
// Usage: php bin/create-admin.php you@example.com "Your Name"
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
require dirname(__DIR__) . '/app/helpers/bootstrap.php';
$email = filter_var($argv[1] ?? '', FILTER_VALIDATE_EMAIL);
$name = trim($argv[2] ?? '');
if (!$email || $name === '') exit("Usage: php bin/create-admin.php email \"Name\"\n");
echo 'Password (min 12 chars): ';
system('stty -echo 2>/dev/null'); $pw = trim((string)fgets(STDIN)); system('stty echo 2>/dev/null'); echo "\n";
if (strlen($pw) < 12) exit("Password too short.\n");
Database::query('INSERT INTO admin_users (name, email, password_hash, role) VALUES (?,?,?,?)', [$name, $email, password_hash($pw, PASSWORD_DEFAULT), 'admin']);
echo "Admin created.\n";
