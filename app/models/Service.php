<?php
final class Service {
    public static function adminList(string $state = ''): array {
        $where = $state === 'active' ? 'WHERE s.is_active = 1' : ($state === 'inactive' ? 'WHERE s.is_active = 0' : '');
        return Database::query("SELECT s.id, s.slug, s.name, s.pillar, s.show_on_home, s.display_order, s.is_active, s.is_placeholder, s.updated_at,
            (SELECT COUNT(*) FROM service_features f WHERE f.service_id = s.id) AS feature_count FROM services s $where ORDER BY FIELD(s.pillar,'build','create','launch'), s.display_order, s.id")->fetchAll();
    }
    public static function options(): array { return Database::query("SELECT id, name, pillar FROM services WHERE is_active = 1 ORDER BY FIELD(pillar,'build','create','launch'), display_order, id")->fetchAll(); }
    public static function find(int $id): ?array { return Database::query('SELECT * FROM services WHERE id = ?', [$id])->fetch() ?: null; }
    public static function features(int $id): array { return Database::query('SELECT label FROM service_features WHERE service_id = ? ORDER BY display_order, id', [$id])->fetchAll(PDO::FETCH_COLUMN); }
    /** $features: list of labels in display order. */
    public static function save(?int $id, array $d, array $features): int {
        return (int)Database::transaction(function () use ($id, $d, $features) {
            $nul = fn($v) => ($v === '' || $v === null) ? null : $v;
            if ($id && !self::find($id)) throw new RuntimeException('Service not found');
            $v = [$d['slug'], $d['name'], $d['pillar'], $nul($d['description']), $d['visual_media_id'] ?: null, $d['show_on_home'], $d['display_order'], $d['is_active'], $d['is_placeholder']];
            if (!$id) {
                Database::query('INSERT INTO services (slug,name,pillar,description,visual_media_id,show_on_home,display_order,is_active,is_placeholder) VALUES (?,?,?,?,?,?,?,?,?)', $v);
                $id = (int)Database::pdo()->lastInsertId();
            } else Database::query('UPDATE services SET slug=?,name=?,pillar=?,description=?,visual_media_id=?,show_on_home=?,display_order=?,is_active=?,is_placeholder=? WHERE id=?', [...$v, $id]);
            Database::query('DELETE FROM service_features WHERE service_id = ?', [$id]);
            foreach (array_values($features) as $i => $label) Database::query('INSERT INTO service_features (service_id, label, display_order) VALUES (?,?,?)', [$id, $label, $i]);
            return $id;
        });
    }
    public static function setActive(int $id, bool $active): void { Database::query('UPDATE services SET is_active = ? WHERE id = ?', [$active ? 1 : 0, $id]); }
    public static function delete(int $id): bool { return Database::query('DELETE FROM services WHERE id = ? AND is_active = 0', [$id])->rowCount() === 1; }
}
