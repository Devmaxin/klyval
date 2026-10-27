<?php // $p (project), $extra (bool, optional), $i (display index, optional), $clone (bool: never used server-side) ?>
<li class="wall__item<?= !empty($extra) ? ' wall__item--extra' : '' ?>" style="--i:<?= (int)($i ?? 0) ?>">
  <a class="card" href="/work/<?= e($p['slug']) ?>" data-cursor="VIEW">
    <span class="card__img ph" style="aspect-ratio:<?= img_ratio($p['image_w'], $p['image_h'], .8, 2.4) ?>">
      <?php if ($p['image']): ?><?= kv_img($p['image'], $p['image_w'], $p['image_h'], $p['image_alt']) ?>
      <?php else: ?><span class="ph__label"><?= e($p['name']) ?></span><?php endif ?>
    </span>
    <span class="card__cap"><span class="card__name"><?= e($p['name']) ?></span><?php if ($p['category'] !== ''): ?><span class="card__cat"><?= e($p['category']) ?></span><?php endif ?></span>
  </a>
</li>
