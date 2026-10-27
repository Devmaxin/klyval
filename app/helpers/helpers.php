<?php
declare(strict_types=1);

function e(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
/** HTTPS behind the platform's TLS-terminating proxy (the container itself sees plain HTTP). */
function is_https(): bool {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}
/** Absolute URL. Uses APP_URL; if unset falls back to the server's own configured name (never the client-supplied Host header). */
function url(string $path = '/'): string {
    if (preg_match('#^https?://#i', $path)) return $path;   // absolute URLs (e.g. Storage media) pass through
    $base = rtrim((string)Env::get('APP_URL', ''), '/');
    if ($base === '' && !empty($_SERVER['SERVER_NAME'])) $base = (is_https() ? 'https://' : 'http://') . $_SERVER['SERVER_NAME'];
    return $base . '/' . ltrim($path, '/');
}
/** Public URL origin prefix for stored media, no trailing slash. */
function media_base(): string {
    $b = (string)Env::get('MEDIA_BASE_URL', '');
    if ($b !== '') return rtrim($b, '/');
    $s = rtrim((string)Env::get('SUPABASE_URL', ''), '/');
    return $s !== '' ? $s . '/storage/v1/object/public/' . (Env::get('MEDIA_BUCKET') ?: 'media') : '';
}
/** Origin (scheme://host[:port]) of the public media base, for the CSP img-src. */
function media_origin(): string {
    $p = parse_url(media_base());
    if (empty($p['scheme']) || empty($p['host'])) return '';
    return $p['scheme'] . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '');
}
/** Upload size limit in whole MB, shown to admins (the platform request-body cap is smaller than the app's). */
function media_max_mb(): int { return (int)(MediaService::MAX_BYTES / 1048576); }
/** Public URL for one stored media object, preserving the original filename. */
function media_file_url(string $filename): string {
    return media_base() . '/' . rawurlencode($filename);
}
/** JSON-LD block. Not executable script, so it is unaffected by the CSP. Flags prevent breaking out of the tag. */
function json_ld(array $data): string {
    return '<script type="application/ld+json">' . json_encode($data + ['@context' => 'https://schema.org'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . '</script>';
}
function asset(string $path): string {
    $f = ROOT . '/public/assets/' . $path;
    return '/assets/' . $path . (is_file($f) ? '?v=' . filemtime($f) : '');
}
function redirect(string $to): never { header('Location: ' . $to, true, 302); exit; }
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="' . e(Csrf::token()) . '">'; }
function is_active(string $path): bool {
    $p = rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/') ?: '/';
    return $p === $path || ($path !== '/' && str_starts_with($p, $path . '/'));
}
/** Site-wide values: database first (Settings), then .env as a fallback. */
function setting(string $key, ?string $default = null): ?string {
    $v = Settings::get($key);
    if ($v !== null && $v !== '') return $v;
    $e = Env::get(strtoupper($key));
    return ($e !== null && $e !== '') ? $e : $default;
}
function media_url(?array $m): string { return MediaService::url($m); }
/** Split plain text into paragraphs on blank lines. Output with <p><?= nl2br(e($p)) ?></p> (escaped first). */
function paragraphs(?string $t): array {
    return array_values(array_filter(array_map('trim', preg_split('/\n\s*\n/', str_replace("\r", '', (string)$t)) ?: []), fn($p) => $p !== ''));
}
function analytics_tags(): string {
    $p = Settings::get('analytics_provider'); $id = trim((string)Settings::get('analytics_id'));
    if ($p === 'plausible' && preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/i', $id))
        return '<script defer data-domain="' . e($id) . '" src="https://plausible.io/js/script.js"></script>';
    if ($p === 'ga4' && preg_match('/^G-[A-Z0-9]{4,14}$/', $id))
        return '<script async src="https://www.googletagmanager.com/gtag/js?id=' . e($id) . '"></script><script defer src="' . e(asset('js/ga4.js')) . '" data-id="' . e($id) . '"></script>';
    return '';
}

function not_found(): never {
    http_response_code(404);
    View::render('pages/404', ['meta' => ['title' => 'Page not found', 'robots' => 'noindex']]);
    exit;
}

/** CSS aspect-ratio for a stored image, clamped so one extreme crop cannot wreck a layout. Falls back when dimensions are unknown. */
function img_ratio(int $w, int $h, float $min = 0.8, float $max = 2.2, string $fallback = '16 / 10'): string {
    if ($w < 1 || $h < 1) return $fallback;
    return number_format(max($min, min($max, $w / $h)), 3, '.', '');
}
/** <img> with intrinsic size (no layout shift). $alt '' marks it decorative. Returns '' when there is no image. */
function kv_img(?string $src, int $w, int $h, string $alt = '', string $loading = 'lazy'): string {
    if (!$src) return '';
    $dim = ($w > 0 && $h > 0) ? ' width="' . $w . '" height="' . $h . '"' : '';
    return '<img src="' . e($src) . '" alt="' . e($alt) . '"' . $dim . ' loading="' . ($loading === 'eager' ? 'eager' : 'lazy') . '" decoding="async">';
}
