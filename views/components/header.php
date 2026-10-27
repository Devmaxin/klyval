<header class="site-header site-header--<?= e($theme ?? 'dark') ?>" data-nav>
  <a class="brand" href="/" aria-label="Klyval, home"><?php View::partial('logo') ?></a>
  <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="primary-nav"><span class="nav-toggle__text">Menu</span></button>
  <nav class="nav" id="primary-nav" aria-label="Primary">
    <ul class="nav__list">
      <?php foreach (['/work' => 'Work', '/studio' => 'Studio', '/services' => 'Services', '/contact' => 'Contact'] as $href => $label): ?>
        <li><a class="nav__link" href="<?= $href ?>"<?= is_active($href) ? ' aria-current="page"' : '' ?>><?= $label ?></a></li>
      <?php endforeach ?>
    </ul>
    <?php View::partial('button', ['label' => 'Start a project', 'href' => '/contact', 'variant' => 'primary', 'arrow' => '↗']) ?>
  </nav>
</header>
