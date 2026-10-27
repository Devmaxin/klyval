<?php // $projects (published only), $label ?>
<div class="strip" data-loop="x" data-speed="20" role="group" aria-label="<?= e($label) ?>">
  <ul class="strip__track" role="list" data-track>
    <?php foreach ($projects as $p): ?>
    <li class="strip__item">
      <a class="strip__link" href="/work/<?= e($p['slug']) ?>" data-cursor="VIEW">
        <span class="strip__img ph"><?php if ($p['image']): ?><?= kv_img($p['image'], $p['image_w'], $p['image_h'], '') ?><?php endif ?></span>
        <span class="strip__name"><?= e($p['name']) ?></span>
      </a>
    </li>
    <?php endforeach ?>
  </ul>
</div>
