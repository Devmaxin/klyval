<?php
final class TeamMember {
    public static function adminList(string $state = ''): array {
        $where = $state === 'active' ? 'WHERE t.is_active = 1' : ($state === 'inactive' ? 'WHERE t.is_active = 0' : '');
        return Database::query("SELECT t.id, t.slug, t.name, t.role, t.featured, t.display_order, t.is_active, t.is_placeholder, t.updated_at, m.filename AS portrait
            FROM team_members t LEFT JOIN media m ON m.id = t.portrait_media_id $where ORDER BY t.display_order, t.id")->fetchAll();
    }
    public static function options(): array { return Database::query('SELECT id, name, role FROM team_members WHERE is_active = 1 ORDER BY display_order, id')->fetchAll(); }
    public static function find(int $id): ?array { return Database::query('SELECT * FROM team_members WHERE id = ?', [$id])->fetch() ?: null; }
    public static function relations(int $id): array {
        return [
            'skills' => implode(', ', Database::query('SELECT s.name FROM team_skills ts JOIN skills s ON s.id = ts.skill_id WHERE ts.member_id = ? ORDER BY s.name', [$id])->fetchAll(PDO::FETCH_COLUMN)),
            'socials' => Database::query('SELECT platform, url FROM team_socials WHERE member_id = ?', [$id])->fetchAll(PDO::FETCH_KEY_PAIR),
            'projects' => array_map('intval', Database::query('SELECT project_id FROM project_team WHERE member_id = ?', [$id])->fetchAll(PDO::FETCH_COLUMN)),
        ];
    }
    /** $rel: skills (names), socials [platform => url], projects [ids]. */
    public static function save(?int $id, array $d, array $rel): int {
        return (int)Database::transaction(function () use ($id, $d, $rel) {
            $nul = fn($v) => ($v === '' || $v === null) ? null : $v;
            $old = $id ? self::find($id) : null;
            if ($id && !$old) throw new RuntimeException('Team member not found');
            $v = [$d['slug'], $d['name'], $d['role'], $d['portrait_media_id'] ?: null, $nul($d['short_bio']), $nul($d['full_bio']), $nul($d['portfolio_url']), $d['featured'], $d['display_order'], $d['is_active'], $d['is_placeholder']];
            if (!$id) {
                $id = Database::insert('INSERT INTO team_members (slug,name,role,portrait_media_id,short_bio,full_bio,portfolio_url,featured,display_order,is_active,is_placeholder) VALUES (?,?,?,?,?,?,?,?,?,?,?)', $v);
            } else {
                Slug::recordChange('team_slug_history', 'member_id', $id, $old['slug'], $d['slug'], (bool)$old['is_active']);
                Database::query('UPDATE team_members SET slug=?,name=?,role=?,portrait_media_id=?,short_bio=?,full_bio=?,portfolio_url=?,featured=?,display_order=?,is_active=?,is_placeholder=? WHERE id=?', [...$v, $id]);
            }
            Database::query('DELETE FROM team_skills WHERE member_id = ?', [$id]);
            foreach (Vocabulary::ids('skills', $rel['skills']) as $sid) Database::query('INSERT INTO team_skills (member_id, skill_id) VALUES (?,?)', [$id, $sid]);
            Database::query('DELETE FROM team_socials WHERE member_id = ?', [$id]);
            $order = 0;
            foreach ($rel['socials'] as $platform => $url) Database::query('INSERT INTO team_socials (member_id, platform, url, display_order) VALUES (?,?,?,?)', [$id, $platform, $url, $order++]);
            // Projects assigned from this side: keep existing rows (and their role labels), add/remove the difference.
            $want = Database::existing('projects', $rel['projects']);
            $have = array_map('intval', Database::query('SELECT project_id FROM project_team WHERE member_id = ?', [$id])->fetchAll(PDO::FETCH_COLUMN));
            foreach (array_diff($have, $want) as $pid) Database::query('DELETE FROM project_team WHERE member_id = ? AND project_id = ?', [$id, $pid]);
            foreach (array_diff($want, $have) as $pid) Database::query('INSERT INTO project_team (project_id, member_id) VALUES (?,?)', [$pid, $id]);
            return $id;
        });
    }
    public static function setActive(int $id, bool $active): void { Database::query('UPDATE team_members SET is_active = ? WHERE id = ?', [$active ? 1 : 0, $id]); }
    /** Permanent delete. Only inactive members can be deleted. */
    public static function delete(int $id): bool { return Database::query('DELETE FROM team_members WHERE id = ? AND is_active = 0', [$id])->rowCount() === 1; }
}
