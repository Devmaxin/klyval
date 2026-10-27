<?php
final class PageController {
    public function home(): void {
        $logo = Settings::media('logo'); $mail = setting('contact_email');
        $org = ['@type' => 'Organization', 'name' => 'Klyval', 'description' => 'Digital studio. We build. We create. We launch.', 'url' => url('/')]
            + ($logo ? ['logo' => url(media_url($logo))] : [])
            + ($mail ? ['email' => $mail] : [])
            + (($same = array_column(Settings::socials(), 'url')) ? ['sameAs' => $same] : []);
        View::render('pages/home', [
            'meta' => [
                'schema' => [$org, ['@type' => 'WebSite', 'name' => 'Klyval', 'url' => url('/')]],
                'title' => 'Klyval — Digital Studio. We build. We create. We launch.',
                'description' => HomeContent::copy()['hero_lede'],
            ],
            'headerTheme' => 'dark',
            'intro' => true,
            'styles' => ['css/intro.css', 'css/home.css'],
            'scripts' => ['js/intro.js', 'js/loop.js', 'js/process.js', 'js/magnetic.js'],
            'c' => HomeContent::all(),
        ]);
    }

    public function studio(): void {
        $copy = HomeContent::copy();
        View::render('pages/studio', [
            'meta' => ['title' => 'Studio — Klyval', 'description' => $copy['studio_text']],
            'headerTheme' => 'light', 'styles' => ['css/home.css', 'css/pages.css'], 'scripts' => ['js/loop.js', 'js/process.js', 'js/magnetic.js'],
            'team' => SiteContent::team(), 'project_index' => array_column(SiteContent::projects(), null, 'slug'), 'process' => HomeContent::process(), 'copy' => $copy, 'services' => SiteContent::pillars(true),
        ]);
    }

    public function services(): void {
        View::render('pages/services', [
            'meta' => ['title' => 'Services — Klyval', 'description' => 'What Klyval does: build websites and digital products, create video and visual content, and launch them well.'],
            'headerTheme' => 'light', 'styles' => ['css/home.css', 'css/pages.css'], 'scripts' => ['js/magnetic.js'],
            'services' => SiteContent::pillars(), 'copy' => HomeContent::copy(),
            'project_index' => array_column(SiteContent::projects(), null, 'slug'), 'pillar_projects' => SiteContent::projectSlugsByPillar(),
        ]);
    }
}
