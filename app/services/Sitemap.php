<?php
/** URLs for sitemap.xml. Only live, non-placeholder content is listed. */
final class Sitemap {
    public static function entries(): array {
        $e = [['/', null], ['/work', null], ['/studio', null], ['/services', null], ['/contact', null]];
        try {
            if (SiteContent::hasArticles()) $e[] = ['/insights', null];
            foreach (Database::query("SELECT slug, updated_at FROM projects WHERE status = 'published' AND is_placeholder = 0 ORDER BY display_order, id")->fetchAll() as $r) $e[] = ['/work/' . $r['slug'], $r['updated_at']];
            foreach (Database::query("SELECT slug, updated_at FROM team_members WHERE is_active = 1 AND is_placeholder = 0 ORDER BY display_order, id")->fetchAll() as $r) $e[] = ['/team/' . $r['slug'], $r['updated_at']];
            foreach (Database::query("SELECT slug, updated_at FROM articles WHERE status = 'published' AND published_at <= NOW() ORDER BY published_at DESC, id DESC")->fetchAll() as $r) $e[] = ['/insights/' . $r['slug'], $r['updated_at']];
        } catch (Throwable $ex) { error_log('Sitemap: ' . $ex->getMessage()); }
        return $e;
    }
}
