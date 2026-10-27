<?php // $p (project), $hide (bool, optional), $lazy (bool, default true) — light-theme card used by Work, Services and project pages ?>
<li class="work-card" data-kind="<?= e($p['kind']) ?>"<?= !empty($hide) ? ' hidden' : '' ?>>
  <a class="wcard" href="/work/<?= e($p['slug']) ?>" data-cursor="VIEW">
    <span class="wcard__img ph" style="aspect-ratio:<?= img_ratio($p['image_w'], $p['image_h'], .8, 2.4) ?>">
      <?php if ($p['image']): ?><?= kv_img($p['image'], $p['image_w'], $p['image_h'], $p['image_alt'], ($lazy ?? true) ? 'lazy' : 'eager') ?>
      <?php else: ?><span class="ph__label"><?= e($p['name']) ?></span><?php endif ?>
    </span>
    <span class="wcard__cap">
      <span class="wcard__name"><?= e($p['name']) ?><?php if (!empty($p['placeholder'])): ?> <span class="ph-tag">Placeholder</span><?php endif ?></span>
      <?php if ($p['category'] !== ''): ?><span class="wcard__cat"><?= e($p['category']) ?></span><?php endif ?>
    </span>
  </a>
</li>
