<?php
/** Content-Security-Policy. Analytics hosts are added by code from a fixed provider list, never from free text. */
final class Csp {
    public static function build(bool $admin): string {
        $script = "'self'"; $connect = "'self'"; $img = "'self' data:";
        if (!$admin && analytics_tags() !== '') {
            if (Settings::get('analytics_provider') === 'plausible') { $script .= ' https://plausible.io'; $connect .= ' https://plausible.io'; }
            else { $script .= ' https://www.googletagmanager.com'; $connect .= ' https://*.google-analytics.com https://*.analytics.google.com https://www.googletagmanager.com'; $img .= ' https://*.google-analytics.com'; }
        }
        return "default-src 'self'; img-src $img; font-src 'self'; style-src 'self'; style-src-attr 'unsafe-inline'; script-src $script; connect-src $connect; frame-ancestors 'none'; base-uri 'self'; form-action 'self'";
    }
}
