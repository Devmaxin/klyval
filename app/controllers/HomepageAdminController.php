<?php
final class HomepageAdminController extends AdminBase {
    private function formData(array $v, array $stats, array $errors, array $featured): void {
        $this->render('homepage', [
            'title' => 'Homepage', 'v' => $v, 'errors' => $errors, 'stats' => $stats, 'featured' => $featured,
            'projects' => Database::query("SELECT id, name, status, featured FROM projects WHERE status <> 'archived' ORDER BY display_order, id")->fetchAll(),
            'team' => Database::query('SELECT id, name, role, featured FROM team_members WHERE is_active = 1 ORDER BY display_order, id')->fetchAll(),
            'services' => Database::query('SELECT id, name, pillar, show_on_home FROM services WHERE is_active = 1 ORDER BY array_position(ARRAY[\'build\',\'create\',\'launch\']::text[], pillar), display_order, id')->fetchAll(),
        ]);
    }
    public function show(): void {
        $this->formData(Settings::homeRaw(), Database::query('SELECT label, value, display_order, is_active FROM homepage_stats ORDER BY display_order, id')->fetchAll(), [], []);
    }
    public function save(): void {
        $f = new Form($_POST);
        $d = [
            'hero_lede' => $f->text('hero_lede', 'Supporting hero copy', 300, true), 'studio_title' => $f->str('studio_title', 'Studio headline', 80, true), 'studio_text' => $f->text('studio_text', 'Studio text', 400, true),
            'cta_title' => $f->str('cta_title', 'Closing headline', 80, true), 'cta_text' => $f->str('cta_text', 'Closing text', 160, true),
            'banner_text' => $f->str('banner_text', 'Banner text', 160), 'banner_url' => $f->url('banner_url', 'Banner link'), 'banner_active' => (string)$f->bool('banner_active'),
        ];
        foreach (['services', 'work', 'studio', 'team', 'process', 'stats'] as $k) $d["show_$k"] = (string)$f->bool("show_$k");
        if ($d['banner_active'] === '1' && $d['banner_text'] === '') $f->errors['banner_text'] = 'Add the banner text or switch the banner off.';

        $L = post_map('stat_label'); $V = post_map('stat_value'); $O = post_map('stat_order'); $A = post_map('stat_active'); $stats = [];
        foreach ($L as $i => $label) {
            $label = trim((string)(is_string($label) ? $label : '')); $val = trim((string)(is_string($V[$i] ?? null) ? $V[$i] : ''));
            if ($label === '' && $val === '') continue;
            if ($label === '' || $val === '' || mb_strlen($label) > 80 || mb_strlen($val) > 40) { $f->errors['stats'] = 'Each statistic needs a label (80 characters max) and a value (40 max). Use real figures only.'; continue; }
            $stats[] = ['label' => $label, 'value' => $val, 'display_order' => max(0, min(1000000, (int)(is_string($O[$i] ?? null) ? $O[$i] : 0))), 'is_active' => !empty($A[$i]) ? 1 : 0];
        }
        $featured = ['projects' => post_ints('featured_projects'), 'team' => post_ints('featured_team'), 'services' => post_ints('home_services')];
        if (!$f->ok()) { http_response_code(422); $this->formData($d, $stats, $f->errors, $featured); return; }
        try {
            Database::transaction(function () use ($d, $stats, $featured) {
                Settings::saveHome($d);
                Database::query('UPDATE projects SET featured = 0'); Database::query('UPDATE team_members SET featured = 0'); Database::query('UPDATE services SET show_on_home = 0');
                foreach (Database::existing('projects', $featured['projects']) as $id) Database::query('UPDATE projects SET featured = 1 WHERE id = ?', [$id]);
                foreach (Database::existing('team_members', $featured['team']) as $id) Database::query('UPDATE team_members SET featured = 1 WHERE id = ?', [$id]);
                foreach (Database::existing('services', $featured['services']) as $id) Database::query('UPDATE services SET show_on_home = 1 WHERE id = ?', [$id]);
                Database::query('DELETE FROM homepage_stats');
                foreach ($stats as $s) Database::query('INSERT INTO homepage_stats (label, value, display_order, is_active) VALUES (?,?,?,?)', [$s['label'], $s['value'], $s['display_order'], $s['is_active']]);
            });
            $this->flash('Homepage saved.');
        } catch (Throwable $e) { $this->failed($e); }
        redirect('/admin/homepage');
    }
}
