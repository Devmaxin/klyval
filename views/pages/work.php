<?php View::partial('page-hero', ['title' => 'Work', 'lede' => 'Things we have built, made and launched.', 'theme' => 'deep']) ?>
<section class="theme-soft work-index" aria-label="Projects">
  <div class="wrap">
    <?php if ($kinds): ?>
    <nav class="filters" aria-label="Filter work" data-filters>
      <a href="/work" data-filter=""<?= $active === '' ? ' aria-current="true"' : '' ?>>All work</a>
      <?php foreach ($kinds as $key => $label): ?><a href="/work?kind=<?= e($key) ?>" data-filter="<?= e($key) ?>"<?= $active === $key ? ' aria-current="true"' : '' ?>><?= e($label) ?></a><?php endforeach ?>
    </nav>
    <?php endif ?>
    <p class="sr-only" role="status" aria-live="polite" data-filter-status></p>
    <?php if (!$projects): ?>
      <p class="muted">Nothing here yet.</p>
    <?php else: ?>
      <ul class="work-grid" role="list" data-work-grid>
        <?php foreach ($projects as $i => $p) View::partial('work-card', ['p' => $p, 'hide' => $active !== '' && $p['kind'] !== $active, 'lazy' => $i > 2]) ?>
      </ul>
    <?php endif ?>
  </div>
</section>
<?php View::section('pages/home/cta', ['copy' => $copy]) ?>
