<?php // $label, $href, $variant (primary|ghost), $arrow (default →), $cursor (optional) ?>
<a class="btn btn--<?= e($variant ?? 'primary') ?>" href="<?= e($href) ?>"<?= !empty($cursor) ? ' data-cursor="' . e($cursor) . '"' : '' ?>>
  <span class="btn__label"><?= e($label) ?></span><span class="btn__arrow" aria-hidden="true"><?= e($arrow ?? '→') ?></span>
</a>
