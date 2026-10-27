<?php
/** Client identity helpers. Raw IPs and emails are never stored: only keyed hashes (APP_KEY is required in production). */
final class Security {
    public static function isProduction(): bool {
        return strtolower((string)Env::get('APP_ENV', '')) === 'production';
    }

    private static function key(): string {
        $k = Env::get('APP_KEY');
        if (($k === null || $k === '') && self::isProduction()) {
            // Fail closed: never serve with a compromised fallback key.
            error_log('Security: APP_KEY is missing in production — refusing to run (fail closed).');
            http_response_code(500);
            exit('Application configuration error.');
        }
        return ($k !== null && $k !== '') ? $k : 'klyval-unset-app-key';
    }

    public static function hash(string $v): string { return hash_hmac('sha256', $v, self::key()); }
    public static function ipHash(): string { return self::hash('ip|' . self::ip()); }
    public static function emailHash(string $email): string { return self::hash('email|' . strtolower(trim($email))); }

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
}
