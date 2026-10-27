<?php
/** Site settings, homepage copy and site-level media. Keys are whitelisted: nothing creates keys from user input. */
final class Settings {
    public const SITE_KEYS = ['contact_email', 'phone', 'whatsapp', 'address', 'seo_default_title', 'seo_default_description', 'analytics_provider', 'analytics_id', 'footer_text', 'maintenance_mode', 'maintenance_message'];
    public const HOME_DEFAULTS = [
        'hero_lede' => 'We build digital experiences, create meaningful content, and help ambitious ideas launch.',
        'studio_title' => "We're a small studio.",
        'studio_text' => 'Developers, designers, creators and problem-solvers building things people actually use.',
        'cta_title' => 'Have something to build?',
        'cta_text' => "Tell us what you're working on.",
        'banner_text' => '', 'banner_url' => '', 'banner_active' => '0',
        'show_services' => '1', 'show_work' => '1', 'show_studio' => '1', 'show_team' => '1', 'show_process' => '1', 'show_stats' => '1',
    ];
    private static ?array $site = null;
    private static ?array $home = null;

    private static function load(string $table): array {
        try { return Database::query("SELECT setting_key, setting_value FROM $table")->fetchAll(PDO::FETCH_KEY_PAIR); }
        catch (Throwable $e) { return []; } // fresh install or DB down: fall back to defaults, never expose the error
    }
    public static function site(): array { return self::$site ??= self::load('site_settings'); }
    public static function get(string $key, ?string $default = null): ?string { $v = self::site()[$key] ?? null; return ($v === null || $v === '') ? $default : $v; }
    public static function home(): array { return array_merge(self::HOME_DEFAULTS, array_filter(self::$home ??= self::load('homepage_settings'), fn($v) => $v !== null && $v !== '' )) + self::HOME_DEFAULTS; }
    /** Raw homepage values (so an intentionally blank banner is not replaced by a default). */
    public static function homeRaw(): array { return array_merge(self::HOME_DEFAULTS, self::load('homepage_settings')); }

    public static function save(string $table, array $allowed, array $kv): void {
        foreach ($kv as $k => $v) {
            if (!in_array($k, $allowed, true)) continue;
            Database::query("INSERT INTO $table (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)", [$k, (string)$v]);
        }
        self::$site = self::$home = null;
    }
    public static function saveSite(array $kv): void { self::save('site_settings', self::SITE_KEYS, $kv); }
    public static function saveHome(array $kv): void { self::save('homepage_settings', array_keys(self::HOME_DEFAULTS), $kv); }

    private static array $mediaCache = [];
    public static function media(string $slot): ?array {
        if (array_key_exists($slot, self::$mediaCache)) return self::$mediaCache[$slot];
        try {
            $r = Database::query('SELECT m.id, m.filename, m.alt_text, m.width, m.height FROM site_media s JOIN media m ON m.id = s.media_id WHERE s.slot = ?', [$slot])->fetch();
            return self::$mediaCache[$slot] = ($r ?: null);
        } catch (Throwable $e) { return self::$mediaCache[$slot] = null; }
    }
    public static function setMedia(string $slot, ?int $mediaId): void {
        if (!in_array($slot, ['logo', 'og_image', 'favicon'], true)) return;
        self::$mediaCache = [];
        Database::query('INSERT INTO site_media (slot, media_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE media_id = VALUES(media_id)', [$slot, $mediaId]);
    }
    public static function socials(): array {
        try { return Database::query('SELECT platform, url FROM social_links WHERE is_active = 1 ORDER BY display_order, id')->fetchAll(); }
        catch (Throwable $e) { return []; }
    }
}
