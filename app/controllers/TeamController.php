<?php
final class TeamController {
    public function show(string $slug): void {
        $m = SiteContent::member($slug);
        if (!$m) {
            if ($to = SiteContent::memberRedirect($slug)) { header('Location: /team/' . rawurlencode($to), true, 301); exit; }
            not_found();
        }
        $projects = array_values(array_filter(SiteContent::projects(), fn($p) => in_array($p['slug'], $m['projects'], true)));
        View::render('pages/member', [
            'meta' => ['title' => $m['name'] . ' — Klyval', 'description' => $m['short_bio'] ?: $m['role'], 'og_image' => $m['image'], 'robots' => $m['placeholder'] ? 'noindex' : null],
            'headerTheme' => 'light', 'styles' => ['css/home.css', 'css/pages.css'], 'scripts' => ['js/loop.js', 'js/magnetic.js'],
            'm' => $m, 'projects' => $projects, 'copy' => HomeContent::copy(),
        ]);
    }
}
