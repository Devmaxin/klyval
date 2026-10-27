<?php // $title, $lede (optional), $theme (deep|soft|light), $eyebrow (optional) ?>
<section class="page-hero theme-<?= e($theme ?? 'deep') ?>">
  <div class="wrap page-hero__inner">
    <?php if (!empty($eyebrow)): ?><p class="eyebrow"><?= e($eyebrow) ?></p><?php endif ?>
    <h1 class="page-hero__title"><?= e($title) ?></h1>
    <?php if (!empty($lede)): ?><p class="lede"><?= e($lede) ?></p><?php endif ?>
  </div>
</section>
