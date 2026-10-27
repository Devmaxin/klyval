<?php
final class WorkController {
    public function index(): void {
        $all = SiteContent::projects();
        // Only offer filters that return something. Unknown or empty kinds fall back to All.
        $present = array_intersect_key(Taxonomy::KINDS, array_flip(array_column($all, 'kind')));
        $kind = (string)($_GET['kind'] ?? '');
        View::render('pages/work', [
            'meta' => ['title' => 'Work — Klyval', 'description' => 'Selected work from Klyval: websites, products, content and launches.'],
            'headerTheme' => 'dark', 'styles' => ['css/home.css', 'css/pages.css'], 'scripts' => ['js/work-filter.js', 'js/magnetic.js'],
            'projects' => $all, 'kinds' => count($present) > 1 ? $present : [], 'active' => (count($present) > 1 && isset($present[$kind])) ? $kind : '', 'copy' => HomeContent::copy(),
        ]);
    }

    public function show(string $slug): void {
        $p = SiteContent::project($slug);
        if (!$p) {
            if ($to = SiteContent::projectRedirect($slug)) { header('Location: /work/' . rawurlencode($to), true, 301); exit; }
            not_found();
        }
        $team = array_values(array_filter(SiteContent::team(), fn($m) => in_array($m['slug'], $p['team'], true)));
        View::render('pages/project', [
            'meta' => ['title' => ($p['seo_title'] ?: $p['name'] . ' — Klyval'), 'description' => ($p['seo_description'] ?: $p['description']), 'og_image' => $p['image'], 'robots' => $p['placeholder'] ? 'noindex' : null,
                'schema' => [['@type' => 'CreativeWork', 'name' => $p['name'], 'description' => $p['description'], 'url' => url('/work/' . $p['slug'])] + ($p['image'] ? ['image' => url($p['image'])] : [])]],
            'headerTheme' => 'dark', 'styles' => ['css/home.css', 'css/pages.css'], 'scripts' => ['js/magnetic.js'],
            'p' => $p, 'team' => $team, 'next' => SiteContent::nextProject($p['id']), 'copy' => HomeContent::copy(),
        ]);
    }
}
