<?php
final class SeoController {
    public function sitemap(): void {
        header('Content-Type: application/xml; charset=utf-8');
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach (Sitemap::entries() as [$path, $mod]) {
            echo '  <url><loc>' . htmlspecialchars(url($path), ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</loc>'
                . ($mod ? '<lastmod>' . date('Y-m-d', strtotime((string)$mod)) . '</lastmod>' : '') . "</url>\n";
        }
        echo '</urlset>';
    }
    public function robots(): void {
        header('Content-Type: text/plain; charset=utf-8');
        echo "User-agent: *\nDisallow: /admin\n\nSitemap: " . url('/sitemap.xml') . "\n";
    }
}
