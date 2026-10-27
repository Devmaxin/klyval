<?php View::partial('page-hero', ['title' => 'Insights', 'lede' => 'Notes from the studio.', 'theme' => 'deep']) ?>
<section class="theme-soft work-index">
  <div class="wrap">
    <?php foreach ($articles as $a): ?>
    <a class="row-project" href="/insights/<?= e($a['slug']) ?>">
      <div class="grid">
        <span class="row-project__visual" style="--m:4;--d:5"><?php if ($a['image']): ?><?php View::partial('visual', ['image' => $a['image'], 'ratio' => '16/10', 'alt' => $a['title']]) ?><?php endif ?></span>
        <span class="row-project__text" style="--m:4;--d:7">
          <span class="row-project__meta"><?= e($a['category']) ?> · <?= e(date('j M Y', strtotime($a['published_at']))) ?></span>
          <span class="row-project__name"><?= e($a['title']) ?></span>
          <?php if ($a['excerpt'] !== ''): ?><span class="row-project__desc"><?= e($a['excerpt']) ?></span><?php endif ?>
        </span>
      </div>
    </a>
    <?php endforeach ?>
  </div>
</section>
<?php View::section('pages/home/cta', ['copy' => $copy]) ?>
