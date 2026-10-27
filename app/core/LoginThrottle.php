<?php
/** Database-backed login throttle: per client address AND per account, in a 15 minute window. Survives new sessions and cookie-less bots. */
final class LoginThrottle {
    public const WINDOW = 900;       // seconds
    public const MAX_PER_IP = 10;    // failed attempts from one address
    public const MAX_PER_ACCOUNT = 8; // failed attempts against one email, from anywhere

    private static function count(string $col, string $hash): int {
        return (int)Database::query("SELECT COUNT(*) FROM login_attempts WHERE $col = ? AND succeeded = 0 AND created_at >= (NOW() - INTERVAL " . self::WINDOW . ' SECOND)', [$hash])->fetchColumn();
    }
    public static function blocked(string $email): bool {
        if (self::count('ip_hash', Security::ipHash()) >= self::MAX_PER_IP) return true;
        return $email !== '' && self::count('email_hash', Security::emailHash($email)) >= self::MAX_PER_ACCOUNT;
    }
    public static function fail(string $email): void {
        Database::query('INSERT INTO login_attempts (ip_hash, email_hash, succeeded) VALUES (?, ?, 0)', [Security::ipHash(), $email !== '' ? Security::emailHash($email) : null]);
        if (random_int(1, 50) === 1) Database::query('DELETE FROM login_attempts WHERE created_at < (NOW() - INTERVAL 2 DAY)');
    }
    /** A successful sign-in clears that account's failures (the address history stays). */
    public static function success(string $email): void {
        if ($email !== '') Database::query('DELETE FROM login_attempts WHERE email_hash = ?', [Security::emailHash($email)]);
    }
}
