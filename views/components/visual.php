<?php // $image (nullable), $ratio (css), $label ?>
<span class="ph" style="aspect-ratio:<?= e($ratio ?? '16/10') ?>;--ph-bg:<?= e($bg ?? 'rgba(8,27,58,.07)') ?>">
  <?php if (!empty($image)): ?><img src="<?= e($image) ?>" alt="<?= e($alt ?? '') ?>" loading="lazy">
  <?php else: ?><span class="ph__label"><?= e($label ?? 'Image placeholder') ?></span><?php endif ?>
</span>
