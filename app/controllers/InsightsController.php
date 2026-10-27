<?php
final class InsightsController {
    public function index(): void {
        $articles = SiteContent::articles();
        if (!$articles) not_found();   // no published articles: the section does not exist yet
        View::render('pages/insights', [
            'meta' => ['title' => 'Insights — Klyval', 'description' => 'Notes from the studio on building, creating and launching.'],
            'headerTheme' => 'dark', 'styles' => ['css/home.css', 'css/pages.css'], 'scripts' => ['js/magnetic.js'],
            'articles' => $articles, 'copy' => HomeContent::copy(),
        ]);
    }
    public function show(string $slug): void {
        $a = SiteContent::article($slug);
        if (!$a) {
            if ($to = SiteContent::articleRedirect($slug)) { header('Location: /insights/' . rawurlencode($to), true, 301); exit; }
            not_found();
        }
        $schema = ['@type' => 'Article', 'headline' => $a['title'], 'datePublished' => date('c', strtotime($a['published_at'])), 'dateModified' => date('c', strtotime($a['updated_at'])),
            'mainEntityOfPage' => url('/insights/' . $a['slug'])]
            + ($a['author'] ? ['author' => ['@type' => 'Person', 'name' => $a['author']]] : ['author' => ['@type' => 'Organization', 'name' => 'Klyval']])
            + ($a['image'] ? ['image' => url($a['image'])] : []);
        View::render('pages/article', [
            'meta' => ['title' => ($a['seo_title'] ?: $a['title'] . ' — Klyval'), 'description' => ($a['seo_description'] ?: $a['excerpt']), 'og_type' => 'article', 'og_image' => $a['image'], 'schema' => [$schema]],
            'headerTheme' => 'dark', 'styles' => ['css/home.css', 'css/pages.css'], 'scripts' => ['js/magnetic.js'],
            'a' => $a, 'copy' => HomeContent::copy(),
        ]);
    }
}
