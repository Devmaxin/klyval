<?php
final class Env {
    private static array $v = [];
    public static function load(string $file): void {
        if (!is_file($file)) return;
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $l) {
            $l = trim($l);
            if ($l === '' || $l[0] === '#' || !str_contains($l, '=')) continue;
            [$k, $v] = explode('=', $l, 2);
            self::$v[trim($k)] = trim($v, " \t\"'");
        }
    }
    public static function get(string $k, ?string $d = null): ?string {
        $e = getenv($k); // real environment wins over .env (used by the smoke test)
        return $e !== false ? $e : (self::$v[$k] ?? $d);
    }
}
