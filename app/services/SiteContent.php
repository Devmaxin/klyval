<?php
/** Public read side. Returns plain arrays shaped for the views. Only published / active records ever leave this class. */
final class SiteContent {
    private static function img(?string $f): ?string { return $f ? media_file_url($f) : null; }
    private static function safe(callable $fn, mixed $fallback): mixed {
        try { return $fn(); } catch (Throwable $e) { error_log('SiteContent: ' . $e->getMessage()); return $fallback; }
    }
    private static function in(array $ids): string { return implode(',', array_fill(0, count($ids), '?')); }
    /** @return array<int, list<string>> grouped by first column */
    private static function group(string $sql, array $ids): array {
        $out = [];
        if (!$ids) return $out;
        foreach (Database::query($sql . ' ', $ids)->fetchAll(PDO::FETCH_NUM) as [$k, $v]) $out[(int)$k][] = $v;
        return $out;
    }

    // ---------- projects ----------
    private static function hydrateProjects(array $rows): array {
        if (!$rows) return [];
        $ids = array_map(fn($r) => (int)$r['id'], $rows); $in = self::in($ids);
        $svc = self::group("SELECT ps.project_id, s.name FROM project_services ps JOIN services s ON s.id = ps.service_id WHERE s.is_active = 1 AND ps.project_id IN ($in) ORDER BY s.display_order, s.id", $ids);
        $tech = self::group("SELECT pt.project_id, t.name FROM project_technologies pt JOIN technologies t ON t.id = pt.technology_id WHERE pt.project_id IN ($in) ORDER BY t.name", $ids);
        $team = self::group("SELECT pt.project_id, tm.slug FROM project_team pt JOIN team_members tm ON tm.id = pt.member_id WHERE tm.is_active = 1 AND pt.project_id IN ($in) ORDER BY pt.display_order, tm.display_order, tm.id", $ids);
        $gal = [];
        foreach (Database::query("SELECT pm.project_id, m.filename, m.width, m.height, m.alt_text, pm.caption FROM project_media pm JOIN media m ON m.id = pm.media_id WHERE pm.project_id IN ($in) ORDER BY pm.display_order, m.id", $ids)->fetchAll() as $g)
            $gal[(int)$g['project_id']][] = ['src' => self::img($g['filename']), 'w' => (int)$g['width'], 'h' => (int)$g['height'], 'alt' => (string)$g['alt_text'], 'caption' => (string)$g['caption']];
        $roles = [];
        foreach (Database::query("SELECT pt.project_id, tm.slug, pt.role_label FROM project_team pt JOIN team_members tm ON tm.id = pt.member_id WHERE tm.is_active = 1 AND pt.role_label IS NOT NULL AND pt.project_id IN ($in)", $ids)->fetchAll() as $t)
            $roles[(int)$t['project_id']][$t['slug']] = $t['role_label'];
        return array_map(function ($r) use ($svc, $tech, $team, $gal, $roles) {
            $id = (int)$r['id'];
            return ['id' => $id, 'slug' => $r['slug'], 'name' => $r['name'], 'client' => (string)$r['client_name'], 'kind' => $r['kind'], 'category' => Taxonomy::KINDS[$r['kind']] ?? '',
                'description' => (string)$r['description'], 'case_study' => paragraphs($r['case_study']), 'image' => self::img($r['cover_file'] ?? null), 'image_w' => (int)($r['cover_w'] ?? 0), 'image_h' => (int)($r['cover_h'] ?? 0), 'image_alt' => (string)($r['cover_alt'] ?? ''), 'video' => $r['video_url'], 'url' => $r['project_url'],
                'seo_title' => $r['seo_title'], 'seo_description' => $r['seo_description'], 'services' => $svc[$id] ?? [], 'technologies' => $tech[$id] ?? [], 'team' => $team[$id] ?? [], 'team_roles' => $roles[$id] ?? [],
                'gallery' => array_column($gal[$id] ?? [], 'src'), 'gallery_items' => $gal[$id] ?? [], 'placeholder' => (bool)$r['is_placeholder']];
        }, $rows);
    }
    private const PROJECT_SELECT = "SELECT p.*, m.filename AS cover_file, m.width AS cover_w, m.height AS cover_h, m.alt_text AS cover_alt FROM projects p LEFT JOIN media m ON m.id = p.cover_media_id WHERE p.status = 'published'";

    public static function projects(string $kind = '', ?int $limit = null, bool $featuredOnly = false): array {
        return self::safe(function () use ($kind, $limit, $featuredOnly) {
            $sql = self::PROJECT_SELECT; $a = [];
            if (isset(Taxonomy::KINDS[$kind])) { $sql .= ' AND p.kind = ?'; $a[] = $kind; }
            if ($featuredOnly) $sql .= ' AND p.featured = 1';
            $sql .= ' ORDER BY p.display_order, p.id' . ($limit ? ' LIMIT ' . (int)$limit : '');
            return self::hydrateProjects(Database::query($sql, $a)->fetchAll());
        }, []);
    }
    public static function project(string $slug): ?array {
        return self::safe(fn() => self::hydrateProjects(Database::query(self::PROJECT_SELECT . ' AND p.slug = ?', [$slug])->fetchAll())[0] ?? null, null);
    }
    public static function projectRedirect(string $slug): ?string {
        return self::safe(fn() => Database::query("SELECT p.slug FROM project_slug_history h JOIN projects p ON p.id = h.project_id WHERE h.old_slug = ? AND p.status = 'published'", [$slug])->fetchColumn() ?: null, null);
    }
    public static function nextProject(int $id): ?array {
        return self::safe(function () use ($id) {
            $ids = array_map('intval', Database::query("SELECT id FROM projects WHERE status = 'published' ORDER BY display_order, id")->fetchAll(PDO::FETCH_COLUMN));
            $i = array_search($id, $ids, true);
            if ($i === false || count($ids) < 2) return null;
            $nid = $ids[($i + 1) % count($ids)];
            return self::hydrateProjects(Database::query(self::PROJECT_SELECT . ' AND p.id = ?', [$nid])->fetchAll())[0] ?? null;
        }, null);
    }

    // ---------- team ----------
    private const TEAM_SELECT = 'SELECT t.*, m.filename AS portrait_file FROM team_members t LEFT JOIN media m ON m.id = t.portrait_media_id WHERE t.is_active = 1';
    private static function hydrateTeam(array $rows): array {
        if (!$rows) return [];
        $ids = array_map(fn($r) => (int)$r['id'], $rows); $in = self::in($ids);
        $skills = self::group("SELECT ts.member_id, s.name FROM team_skills ts JOIN skills s ON s.id = ts.skill_id WHERE ts.member_id IN ($in) ORDER BY s.name", $ids);
        $projects = self::group("SELECT pt.member_id, p.slug FROM project_team pt JOIN projects p ON p.id = pt.project_id WHERE p.status = 'published' AND pt.member_id IN ($in) ORDER BY p.display_order, p.id", $ids);
        $soc = [];
        foreach (Database::query("SELECT member_id, platform, url FROM team_socials WHERE member_id IN ($in) ORDER BY display_order, id", $ids)->fetchAll() as $s) $soc[(int)$s['member_id']][Taxonomy::PLATFORMS[$s['platform']] ?? $s['platform']] = $s['url'];
        return array_map(function ($r) use ($skills, $projects, $soc) {
            $id = (int)$r['id'];
            return ['id' => $id, 'slug' => $r['slug'], 'name' => $r['name'], 'role' => $r['role'], 'image' => self::img($r['portrait_file'] ?? null), 'short_bio' => (string)$r['short_bio'],
                'bio' => paragraphs($r['full_bio']), 'portfolio_url' => $r['portfolio_url'], 'skills' => $skills[$id] ?? [], 'socials' => $soc[$id] ?? [], 'projects' => $projects[$id] ?? [], 'placeholder' => (bool)$r['is_placeholder']];
        }, $rows);
    }
    public static function team(bool $featuredOnly = false, ?int $limit = null): array {
        return self::safe(function () use ($featuredOnly, $limit) {
            $sql = self::TEAM_SELECT . ($featuredOnly ? ' AND t.featured = 1' : '') . ' ORDER BY t.display_order, t.id' . ($limit ? ' LIMIT ' . (int)$limit : '');
            return self::hydrateTeam(Database::query($sql)->fetchAll());
        }, []);
    }
    public static function member(string $slug): ?array {
        return self::safe(fn() => self::hydrateTeam(Database::query(self::TEAM_SELECT . ' AND t.slug = ?', [$slug])->fetchAll())[0] ?? null, null);
    }
    public static function memberRedirect(string $slug): ?string {
        return self::safe(fn() => Database::query('SELECT t.slug FROM team_slug_history h JOIN team_members t ON t.id = h.member_id WHERE h.old_slug = ? AND t.is_active = 1', [$slug])->fetchColumn() ?: null, null);
    }

    // ---------- services (grouped under the three fixed pillars) ----------
    public static function pillars(bool $homeOnly = false): array {
        return self::safe(function () use ($homeOnly) {
            $rows = Database::query('SELECT s.*, m.filename AS visual_file FROM services s LEFT JOIN media m ON m.id = s.visual_media_id WHERE s.is_active = 1' . ($homeOnly ? ' AND s.show_on_home = 1' : '') . ' ORDER BY s.display_order, s.id')->fetchAll();
            $ids = array_map(fn($r) => (int)$r['id'], $rows);
            $feat = $ids ? self::group('SELECT service_id, label FROM service_features WHERE service_id IN (' . self::in($ids) . ') ORDER BY display_order, id', $ids) : [];
            $out = [];
            foreach (Taxonomy::PILLARS as $key => $p) $out[$key] = ['slug' => $key, 'name' => $p['label'], 'lead' => $p['lead'], 'items' => [], 'services' => []];
            foreach ($rows as $r) {
                $out[$r['pillar']]['items'][] = $r['name'];
                $out[$r['pillar']]['services'][] = ['slug' => $r['slug'], 'name' => $r['name'], 'description' => paragraphs($r['description']), 'features' => $feat[(int)$r['id']] ?? [], 'visual' => self::img($r['visual_file'] ?? null), 'placeholder' => (bool)$r['is_placeholder']];
            }
            return array_values($out);
        }, array_values(array_map(fn($k, $p) => ['slug' => $k, 'name' => $p['label'], 'lead' => $p['lead'], 'items' => [], 'services' => []], array_keys(Taxonomy::PILLARS), Taxonomy::PILLARS)));
    }
    /** pillar key => slugs of published projects linked (project_services) to an active service in that pillar. Real links only. */
    public static function projectSlugsByPillar(): array {
        return self::safe(function () {
            $out = [];
            $rows = Database::query("SELECT DISTINCT s.pillar, p.slug, p.display_order, p.id FROM project_services ps JOIN services s ON s.id = ps.service_id AND s.is_active = 1 JOIN projects p ON p.id = ps.project_id AND p.status = 'published' ORDER BY p.display_order, p.id")->fetchAll();
            foreach ($rows as $r) $out[$r['pillar']][] = $r['slug'];
            return $out;
        }, []);
    }
    public static function stats(): array {
        return self::safe(fn() => Database::query('SELECT label, value FROM homepage_stats WHERE is_active = 1 ORDER BY display_order, id')->fetchAll(), []);
    }

    // ---------- insights ----------
    private const ARTICLE_SELECT = "SELECT a.*, m.filename AS cover_file, t.name AS author_name, t.slug AS author_slug FROM articles a LEFT JOIN media m ON m.id = a.cover_media_id LEFT JOIN team_members t ON t.id = a.author_id AND t.is_active = 1 WHERE a.status = 'published' AND a.published_at <= NOW()";
    private static function shapeArticle(array $r): array {
        return ['id' => (int)$r['id'], 'slug' => $r['slug'], 'title' => $r['title'], 'excerpt' => (string)$r['excerpt'], 'content' => paragraphs($r['content']), 'image' => self::img($r['cover_file'] ?? null),
            'category' => Taxonomy::ARTICLE_CATEGORIES[$r['category']] ?? '', 'published_at' => $r['published_at'], 'updated_at' => $r['updated_at'], 'author' => $r['author_name'], 'author_slug' => $r['author_slug'],
            'seo_title' => $r['seo_title'], 'seo_description' => $r['seo_description']];
    }
    public static function articles(?int $limit = null): array {
        return self::safe(fn() => array_map([self::class, 'shapeArticle'], Database::query(self::ARTICLE_SELECT . ' ORDER BY a.published_at DESC, a.id DESC' . ($limit ? ' LIMIT ' . (int)$limit : ''))->fetchAll()), []);
    }
    public static function article(string $slug): ?array {
        return self::safe(function () use ($slug) { $r = Database::query(self::ARTICLE_SELECT . ' AND a.slug = ?', [$slug])->fetch(); return $r ? self::shapeArticle($r) : null; }, null);
    }
    public static function articleRedirect(string $slug): ?string {
        return self::safe(fn() => Database::query("SELECT a.slug FROM article_slug_history h JOIN articles a ON a.id = h.article_id WHERE h.old_slug = ? AND a.status = 'published' AND a.published_at <= NOW()", [$slug])->fetchColumn() ?: null, null);
    }
    public static function hasArticles(): bool {
        return self::safe(fn() => (bool)Database::query("SELECT 1 FROM articles WHERE status = 'published' AND published_at <= NOW() LIMIT 1")->fetchColumn(), false);
    }
}
