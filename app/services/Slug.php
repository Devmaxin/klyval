<?php
final class Slug {
    public static function make(string $s): string {
        if (function_exists('iconv')) { $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s); if ($t !== false) $s = $t; }
        $s = trim((string)preg_replace('/[^a-z0-9]+/', '-', strtolower($s)), '-');
        return substr($s, 0, 120) ?: 'item';
    }
    /** Is the slug used by another record or reserved by another record's slug history? Table names come from code only. */
    public static function taken(string $table, ?string $histTable, string $histCol, string $slug, ?int $ignoreId): bool {
        $id = $ignoreId ?? 0;
        if (Database::query("SELECT 1 FROM $table WHERE slug = ? AND id <> ? LIMIT 1", [$slug, $id])->fetch()) return true;
        return $histTable && Database::query("SELECT 1 FROM $histTable WHERE old_slug = ? AND $histCol <> ? LIMIT 1", [$slug, $id])->fetch();
    }
    public static function unique(string $base, string $table, ?string $histTable, string $histCol, ?int $ignoreId): string {
        $slug = $base; $n = 2;
        while (self::taken($table, $histTable, $histCol, $slug, $ignoreId)) { $slug = substr($base, 0, 110) . '-' . $n++; }
        return $slug;
    }
    /** Called on update: remember the old public slug so it can redirect, and release it if the record is returning to it. */
    public static function recordChange(string $histTable, string $histCol, int $id, string $old, string $new, bool $wasPublic): void {
        if ($old === $new) return;
        if ($wasPublic) Database::query("INSERT IGNORE INTO $histTable ($histCol, old_slug) VALUES (?, ?)", [$id, $old]);
        Database::query("DELETE FROM $histTable WHERE old_slug = ? AND $histCol = ?", [$new, $id]);
    }
}
