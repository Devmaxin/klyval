<?php
/** technologies and skills are separate tables on purpose. $table is always a code constant, never user input. */
final class Vocabulary {
    public static function parse(string $csv, int $limit = 20): array {
        $out = [];
        foreach (explode(',', $csv) as $n) {
            $n = trim(preg_replace('/\s+/', ' ', $n) ?? '');
            if ($n !== '' && mb_strlen($n) <= 80) $out[mb_strtolower($n)] = $n;
        }
        return array_slice(array_values($out), 0, $limit);
    }
    /** Find-or-create each name, return ids. Call inside the caller's transaction. */
    public static function ids(string $table, array $names): array {
        $ids = [];
        foreach ($names as $name) {
            $row = Database::query("SELECT id FROM $table WHERE name = ?", [$name])->fetch();
            if ($row) { $ids[] = (int)$row['id']; continue; }
            $slug = $base = Slug::make($name); $n = 2;
            while (Database::query("SELECT 1 FROM $table WHERE slug = ?", [$slug])->fetch()) $slug = substr($base, 0, 70) . '-' . $n++;
            Database::query("INSERT INTO $table (name, slug) VALUES (?, ?)", [$name, $slug]);
            $ids[] = (int)Database::pdo()->lastInsertId();
        }
        return array_values(array_unique($ids));
    }
}
