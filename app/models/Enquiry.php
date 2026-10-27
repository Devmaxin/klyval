<?php
final class Enquiry {
    public static function list(string $status = '', int $limit = 200): array {
        $where = isset(Taxonomy::ENQUIRY_STATUSES[$status]) ? 'WHERE status = ?' : '';
        return Database::query("SELECT id, name, company, email, project_type, status, created_at FROM contact_submissions $where ORDER BY created_at DESC, id DESC LIMIT " . (int)$limit, $where ? [$status] : [])->fetchAll();
    }
    public static function find(int $id): ?array { return Database::query('SELECT * FROM contact_submissions WHERE id = ?', [$id])->fetch() ?: null; }
    public static function update(int $id, string $status, ?string $notes): void {
        if (!isset(Taxonomy::ENQUIRY_STATUSES[$status])) return;
        Database::query('UPDATE contact_submissions SET status = ?, notes = ? WHERE id = ?', [$status, $notes === '' ? null : $notes, $id]);
    }
    public static function counts(): array {
        $r = Database::query('SELECT status, COUNT(*) c FROM contact_submissions GROUP BY status')->fetchAll(PDO::FETCH_KEY_PAIR);
        $out = []; foreach (Taxonomy::ENQUIRY_STATUSES as $k => $_) $out[$k] = (int)($r[$k] ?? 0);
        return $out;
    }
}
