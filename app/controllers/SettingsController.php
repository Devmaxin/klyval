<?php
final class SettingsController extends AdminBase {
    private function formData(array $old = [], array $errors = []): void {
        $socials = Database::query('SELECT platform, url, is_active FROM social_links')->fetchAll(); $byP = [];
        foreach ($socials as $s) $byP[$s['platform']] = $s;
        $this->render('settings', [
            'title' => 'Settings', 'v' => $old ?: Settings::site(), 'errors' => $errors, 'socials' => $old['socials'] ?? $byP, 'media' => MediaService::all(),
            'slots' => ['logo' => Settings::media('logo')['id'] ?? null, 'og_image' => Settings::media('og_image')['id'] ?? null, 'favicon' => Settings::media('favicon')['id'] ?? null],
        ]);
    }
    public function show(): void { $this->formData(); }

    public function save(): void {
        $f = new Form($_POST);
        $d = [
            'contact_email' => $f->email('contact_email', 'Email'), 'phone' => $f->str('phone', 'Phone', 40), 'whatsapp' => $f->str('whatsapp', 'WhatsApp', 40),
            'address' => $f->text('address', 'Address', 300), 'seo_default_title' => $f->str('seo_default_title', 'Default title', 70),
            'seo_default_description' => $f->str('seo_default_description', 'Default description', 200), 'footer_text' => $f->str('footer_text', 'Footer text', 200),
            'maintenance_message' => $f->text('maintenance_message', 'Maintenance message', 300), 'maintenance_mode' => (string)$f->bool('maintenance_mode'),
        ];
        $provider = $f->enum('analytics_provider', 'analytics provider', ['none', 'plausible', 'ga4'], 'none');
        $aid = $f->str('analytics_id', 'Analytics ID', 60);
        if ($provider === 'plausible' && !preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/i', $aid)) $f->errors['analytics_id'] = 'Enter your site domain, for example example.com.';
        if ($provider === 'ga4' && !preg_match('/^G-[A-Z0-9]{4,14}$/', $aid)) $f->errors['analytics_id'] = 'Enter a GA4 measurement ID such as G-XXXXXXXXXX.';
        if ($provider === 'none') $aid = '';
        $d['analytics_provider'] = $provider; $d['analytics_id'] = $aid;
        if (!empty($_POST['whatsapp']) && !preg_match('/^[\d\s+()-]{6,40}$/', (string)$_POST['whatsapp'])) $f->errors['whatsapp'] = 'Use digits only, with country code.';

        $socials = []; $urls = post_map('social_url'); $act = post_map('social_active');
        foreach (Taxonomy::studioPlatforms() as $p => $label) {
            $u = is_string($urls[$p] ?? null) ? trim($urls[$p]) : '';
            if ($u === '') continue;
            $sf = new Form(['u' => $u]); $sf->url('u', "$label link");
            if (!$sf->ok()) $f->errors["social_$p"] = $sf->errors['u']; else $socials[$p] = ['platform' => $p, 'url' => $u, 'is_active' => !empty($act[$p]) ? 1 : 0];
        }
        if (!$f->ok()) { http_response_code(422); $d['socials'] = $socials; $this->formData($d, $f->errors); return; }

        try {
            Database::transaction(function () use ($d, $socials) {
                Settings::saveSite($d);
                foreach (['logo', 'og_image', 'favicon'] as $slot) Settings::setMedia($slot, $this->mediaId("media_$slot"));
                $keep = array_keys($socials); $order = 0;
                foreach ($socials as $s) Database::query('INSERT INTO social_links (platform, url, display_order, is_active) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE url = VALUES(url), display_order = VALUES(display_order), is_active = VALUES(is_active)', [$s['platform'], $s['url'], $order++, $s['is_active']]);
                if ($keep) Database::query('DELETE FROM social_links WHERE platform NOT IN (' . implode(',', array_fill(0, count($keep), '?')) . ')', $keep);
                else Database::query('DELETE FROM social_links');
            });
            $this->flash('Settings saved.');
        } catch (Throwable $e) { $this->failed($e); }
        redirect('/admin/settings');
    }
}
