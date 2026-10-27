<?php
/** Homepage assembly: admin-controlled copy/visibility from Settings, records from SiteContent. Layout and motion stay in code. */
final class HomeContent {
    public static function copy(): array {
        $h = Settings::home();
        return ['hero_lede' => $h['hero_lede'], 'studio_title' => $h['studio_title'], 'studio_text' => $h['studio_text'], 'cta_title' => $h['cta_title'], 'cta_text' => $h['cta_text']];
    }
    public static function all(): array {
        $h = Settings::home();
        $show = [];
        foreach (['services', 'work', 'studio', 'team', 'process', 'stats'] as $k) $show[$k] = ($h['show_' . $k] ?? '1') === '1';
        $banner = null;
        if ($h['banner_active'] === '1' && trim($h['banner_text']) !== '') {
            $u = $h['banner_url']; $okUrl = $u !== '' && in_array(strtolower((string)parse_url($u, PHP_URL_SCHEME)), ['http', 'https'], true);
            $banner = ['text' => $h['banner_text'], 'url' => $okUrl ? $u : ''];
        }
        $needProjects = $show['work'] || $show['team'];
        $projects = $needProjects ? SiteContent::projects() : [];
        return [
            'copy' => self::copy(), 'show' => $show, 'banner' => $banner,
            'services' => SiteContent::pillars(true),
            // every published project (not only featured) feeds the showcase
            'projects' => $show['work'] ? $projects : [],
            'project_index' => array_column($projects, null, 'slug'),   // slug => project, for team project strips
            'team' => $show['team'] ? SiteContent::team(true, 6) : [],
            'stats' => $show['stats'] ? SiteContent::stats() : [],
            'process' => self::process(),
        ];
    }
    /** The five-step process is part of the Klyval identity and lives in code. */
    public static function process(): array {
        return [
            ['name' => 'Discover', 'text' => 'Understand the problem.'],
            ['name' => 'Build', 'text' => 'Design and engineer the solution.'],
            ['name' => 'Create', 'text' => 'Develop the content and visual experience.'],
            ['name' => 'Launch', 'text' => 'Put it into the world.'],
            ['name' => 'Grow', 'text' => 'Improve, evolve and scale.'],
        ];
    }
}
