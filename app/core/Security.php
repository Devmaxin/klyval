<?php
/** Client identity helpers. Raw IPs and emails are never stored: only keyed hashes (set APP_KEY in .env). */
final class Security {
    public static function ip(): string {
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'cli');
        // Only behind YOUR reverse proxy: it appends the real client address as the LAST X-Forwarded-For entry.
        if (Env::get('TRUSTED_PROXY') === '1' && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $parts = array_map('trim', explode(',', (string)$_SERVER['HTTP_X_FORWARDED_FOR']));
            $last = end($parts);
            if ($last !== false && filter_var($last, FILTER_VALIDATE_IP)) $ip = $last;
        }
        return $ip;
    }
    private static function key(): string { $k = Env::get('APP_KEY'); return ($k !== null && $k !== '') ? $k : 'klyval-unset-app-key'; }
    public static function hash(string $v): string { return hash_hmac('sha256', $v, self::key()); }
    public static function ipHash(): string { return self::hash('ip|' . self::ip()); }
    public static function emailHash(string $email): string { return self::hash('email|' . strtolower(trim($email))); }
}
