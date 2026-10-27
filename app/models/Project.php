<?php
final class Project {
    public static function adminList(string $status = ''): array {
        $where = isset(Taxonomy::PROJECT_STATUSES[$status]) ? 'WHERE p.status = ?' : '';
        return Database::query("SELECT p.id, p.slug, p.name, p.client_name, p.kind, p.status, p.featured, p.display_order, p.is_placeholder, p.updated_at, m.filename AS cover
            FROM projects p LEFT JOIN media m ON m.id = p.cover_media_id $where ORDER BY p.display_order, p.id", $where ? [$status] : [])->fetchAll();
    }
    public static function find(int $id): ?array { return Database::query('SELECT * FROM projects WHERE id = ?', [$id])->fetch() ?: null; }

    public static function relations(int $id): array {
        return [
            'technologies' => implode(', ', Database::query('SELECT t.name FROM project_technologies pt JOIN technologies t ON t.id = pt.technology_id WHERE pt.project_id = ? ORDER BY t.name', [$id])->fetchAll(PDO::FETCH_COLUMN)),
            'team' => array_column(Database::query('SELECT member_id, role_label, display_order FROM project_team WHERE project_id = ?', [$id])->fetchAll(), null, 'member_id'),
            'gallery' => array_column(Database::query('SELECT media_id, display_order, caption FROM project_media WHERE project_id = ?', [$id])->fetchAll(), null, 'media_id'),
            'services' => array_map('intval', Database::query('SELECT service_id FROM project_services WHERE project_id = ?', [$id])->fetchAll(PDO::FETCH_COLUMN)),
        ];
    }

    /** $rel: technologies (names), team [id => [role, order]], gallery [id => [order, caption]], services [ids]. */
    public static function save(?int $id, array $d, array $rel): int {
        return (int)Database::transaction(function () use ($id, $d, $rel) {
            $nul = fn($v) => ($v === '' || $v === null) ? null : $v;
            $old = $id ? self::find($id) : null;
            if ($id && !$old) throw new RuntimeException('Project not found');
            $pub = $old['published_at'] ?? null;
            if ($d['status'] === 'published' && !$pub) $pub = date('Y-m-d H:i:s');
            $v = [$d['slug'], $d['name'], $nul($d['client_name']), $d['kind'], $nul($d['description']), $nul($d['case_study']), $d['cover_media_id'] ?: null,
                  $nul($d['video_url']), $nul($d['project_url']), $d['status'], $d['featured'], $d['display_order'], $nul($d['seo_title']), $nul($d['seo_description']), $d['is_placeholder'], $pub];
            if (!$id) {
                $id = Database::insert('INSERT INTO projects (slug,name,client_name,kind,description,case_study,cover_media_id,video_url,project_url,status,featured,display_order,seo_title,seo_description,is_placeholder,published_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', $v);
            } else {
                Slug::recordChange('project_slug_history', 'project_id', $id, $old['slug'], $d['slug'], $old['status'] !== 'draft');
                Database::query('UPDATE projects SET slug=?,name=?,client_name=?,kind=?,description=?,case_study=?,cover_media_id=?,video_url=?,project_url=?,status=?,featured=?,display_order=?,seo_title=?,seo_description=?,is_placeholder=?,published_at=? WHERE id=?', [...$v, $id]);
            }
            Database::query('DELETE FROM project_technologies WHERE project_id = ?', [$id]);
            foreach (Vocabulary::ids('technologies', $rel['technologies']) as $tid) Database::query('INSERT INTO project_technologies (project_id, technology_id) VALUES (?,?)', [$id, $tid]);
            Database::query('DELETE FROM project_services WHERE project_id = ?', [$id]);
            foreach (Database::existing('services', $rel['services']) as $sid) Database::query('INSERT INTO project_services (project_id, service_id) VALUES (?,?)', [$id, $sid]);
            Database::query('DELETE FROM project_team WHERE project_id = ?', [$id]);
            foreach (Database::existing('team_members', array_keys($rel['team'])) as $mid) {
                $t = $rel['team'][$mid]; Database::query('INSERT INTO project_team (project_id, member_id, role_label, display_order) VALUES (?,?,?,?)', [$id, $mid, $nul($t['role']), $t['order']]);
            }
            Database::query('DELETE FROM project_media WHERE project_id = ?', [$id]);
            foreach (Database::existing('media', array_keys($rel['gallery'])) as $gid) {
                $g = $rel['gallery'][$gid]; Database::query('INSERT INTO project_media (project_id, media_id, display_order, caption) VALUES (?,?,?,?)', [$id, $gid, $g['order'], $nul($g['caption'])]);
            }
            return $id;
        });
    }

    public static function setStatus(int $id, string $status): void {
        if (!isset(Taxonomy::PROJECT_STATUSES[$status])) return;
        Database::query("UPDATE projects SET status = ?, published_at = CASE WHEN ? = 'published' THEN COALESCE(published_at, ?) ELSE published_at END WHERE id = ?", [$status, $status, date('Y-m-d H:i:s'), $id]);
    }
    /** Permanent delete. Only archived projects can be deleted. */
    public static function delete(int $id): bool {
        return Database::query("DELETE FROM projects WHERE id = ? AND status = 'archived'", [$id])->rowCount() === 1;
    }
}
