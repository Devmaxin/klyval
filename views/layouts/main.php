<?php
$m = $meta ?? [];
$title = $m['title'] ?? setting('seo_default_title', 'Klyval — Digital Studio');
$desc = ($m['description'] ?? '') !== '' ? $m['description'] : setting('seo_default_description', 'We build. We create. We launch.');
$og = Settings::media('og_image'); $fav = Settings::media('favicon');
$ogUrl = !empty($m['og_image']) ? url($m['og_image']) : ($og ? url(media_url($og)) : '');
$canon = url($_SERVER['REQUEST_URI'] ? strtok($_SERVER['REQUEST_URI'], '?') : '/');
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?></title>
<meta name="description" content="<?= e($desc) ?>">
<?php if (!empty($m['robots'])): ?><meta name="robots" content="<?= e($m['robots']) ?>"><?php endif ?>
<link rel="canonical" href="<?= e($canon) ?>">
<meta property="og:type" content="<?= e($m['og_type'] ?? 'website') ?>">
<meta property="og:site_name" content="Klyval">
<meta property="og:title" content="<?= e($title) ?>">
<meta property="og:description" content="<?= e($desc) ?>">
<meta property="og:url" content="<?= e($canon) ?>">
<meta name="twitter:card" content="summary_large_image">
<?php if ($ogUrl): ?><meta property="og:image" content="<?= e($ogUrl) ?>"><meta name="twitter:image" content="<?= e($ogUrl) ?>"><?php endif ?>
<?php if ($fav): ?><link rel="icon" href="<?= e(media_url($fav)) ?>"><?php else: ?><link rel="icon" href="/favicon.svg" type="image/svg+xml"><?php endif ?>
<?php foreach (($m['schema'] ?? []) as $sc) echo json_ld($sc), "\n"; ?>
<meta name="theme-color" content="#081B3A">
<script src="<?= asset('js/boot.js') ?>"></script>
<link rel="preload" href="<?= asset('fonts/sora-var.woff2') ?>" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="<?= asset('fonts/inter-var.woff2') ?>" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= asset('css/tokens.css') ?>">
<link rel="stylesheet" href="<?= asset('css/base.css') ?>">
<link rel="stylesheet" href="<?= asset('css/components.css') ?>">
<link rel="stylesheet" href="<?= asset('css/motion.css') ?>">
<?php foreach (($styles ?? []) as $css): ?><link rel="stylesheet" href="<?= asset($css) ?>">
<?php endforeach ?>
<?= analytics_tags() ?>
</head>
<body>
<?php if (!empty($intro)) View::partial('intro') ?>
<a class="skip" href="#main">Skip to content</a>
<?php View::partial('header', ['theme' => $headerTheme ?? 'dark']) ?>
<main id="main"><?= $content ?></main>
<?php View::partial('footer') ?>
<script src="<?= asset('js/motion.js') ?>" defer></script>
<script src="<?= asset('js/nav.js') ?>" defer></script>
<script src="<?= asset('js/cursor.js') ?>" defer></script>
<script src="<?= asset('js/transitions.js') ?>" defer></script>
<?php foreach (($scripts ?? []) as $js): ?><script src="<?= asset($js) ?>" defer></script>
<?php endforeach ?>
</body>
</html>
