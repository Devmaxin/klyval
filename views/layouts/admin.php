<!doctype html><html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($meta['title'] ?? 'Klyval admin') ?></title><meta name="robots" content="noindex,nofollow">
<link rel="stylesheet" href="<?= asset('css/tokens.css') ?>"><link rel="stylesheet" href="<?= asset('css/base.css') ?>">
<link rel="stylesheet" href="<?= asset('css/components.css') ?>"><link rel="stylesheet" href="<?= asset('css/admin.css') ?>">
</head><body class="admin">
<?php if (Auth::check()): $isAdmin = ($_SESSION['admin_role'] ?? '') === 'admin'; ?>
<a class="skip" href="#main">Skip to content</a>
<div class="admin-shell">
  <aside class="admin-side">
    <a href="/admin" class="admin-brand"><?php View::partial('logo') ?></a>
    <nav aria-label="Admin"><ul>
      <?php $items = ['/admin' => 'Dashboard', '/admin/projects' => 'Projects', '/admin/team' => 'Team', '/admin/services' => 'Services', '/admin/insights' => 'Insights', '/admin/homepage' => 'Homepage', '/admin/enquiries' => 'Enquiries', '/admin/media' => 'Media'];
      if ($isAdmin) $items['/admin/settings'] = 'Settings';
      foreach ($items as $href => $label): $on = $href === '/admin' ? rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/') === '/admin' : is_active($href); ?>
        <li><a href="<?= $href ?>"<?= $on ? ' aria-current="page"' : '' ?>><?= $label ?></a></li>
      <?php endforeach ?>
    </ul></nav>
    <p class="admin-who"><?= e($_SESSION['admin_name'] ?? '') ?> · <?= e($_SESSION['admin_role'] ?? '') ?></p>
    <a class="admin-site" href="/" target="_blank" rel="noopener">View site ↗</a>
    <form method="post" action="/admin/logout"><?= csrf_field() ?><button class="btn btn--ghost btn--sm" type="submit">Sign out</button></form>
  </aside>
  <main class="admin-main" id="main">
    <?php if (!empty($_SESSION['flash'])): [$ft, $fm] = $_SESSION['flash']; unset($_SESSION['flash']); ?>
      <p class="flash flash--<?= e($ft) ?>" role="<?= $ft === 'err' ? 'alert' : 'status' ?>"><?= e($fm) ?></p>
    <?php endif ?>
    <?= $content ?>
  </main>
</div>
<?php else: ?><main class="admin-auth" id="main"><?= $content ?></main><?php endif ?>
<script src="<?= asset('js/admin.js') ?>" defer></script>
</body></html>
