<?php
final class Article {
    public static function adminList(string $status = ''): array {
        $where = isset(Taxonomy::PROJECT_STATUSES[$status]) ? 'WHERE a.status = ?' : '';
        return Database::query("SELECT a.id, a.slug, a.title, a.category, a.status, a.published_at, a.updated_at FROM articles a $where ORDER BY COALESCE(a.published_at, a.created_at) DESC, a.id DESC", $where ? [$status] : [])->fetchAll();
    }
    public static function find(int $id): ?array { return Database::query('SELECT * FROM articles WHERE id = ?', [$id])->fetch() ?: null; }
    public static function save(?int $id, array $d): int {
        return (int)Database::transaction(function () use ($id, $d) {
            $nul = fn($v) => ($v === '' || $v === null) ? null : $v;
            $old = $id ? self::find($id) : null;
            if ($id && !$old) throw new RuntimeException('Article not found');
            $pub = $nul($d['published_at']) ?? ($old['published_at'] ?? null);
            if ($d['status'] === 'published' && !$pub) $pub = date('Y-m-d H:i:00');
            $v = [$d['slug'], $d['title'], $nul($d['excerpt']), $nul($d['content']), $d['cover_media_id'] ?: null, $d['author_id'] ?: null, $d['category'], $d['status'], $pub, $nul($d['seo_title']), $nul($d['seo_description'])];
            if (!$id) {
                Database::query('INSERT INTO articles (slug,title,excerpt,content,cover_media_id,author_id,category,status,published_at,seo_title,seo_description) VALUES (?,?,?,?,?,?,?,?,?,?,?)', $v);
                return (int)Database::pdo()->lastInsertId();
            }
            Slug::recordChange('article_slug_history', 'article_id', $id, $old['slug'], $d['slug'], $old['status'] !== 'draft');
            Database::query('UPDATE articles SET slug=?,title=?,excerpt=?,content=?,cover_media_id=?,author_id=?,category=?,status=?,published_at=?,seo_title=?,seo_description=? WHERE id=?', [...$v, $id]);
            return $id;
        });
    }
    public static function setStatus(int $id, string $status): void {
        if (!isset(Taxonomy::PROJECT_STATUSES[$status])) return;
        Database::query("UPDATE articles SET status = ?, published_at = CASE WHEN ? = 'published' THEN COALESCE(published_at, ?) ELSE published_at END WHERE id = ?", [$status, $status, date('Y-m-d H:i:00'), $id]);
    }
    public static function delete(int $id): bool { return Database::query("DELETE FROM articles WHERE id = ? AND status = 'archived'", [$id])->rowCount() === 1; }
}
