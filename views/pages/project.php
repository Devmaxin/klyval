<?php
$hasFacts = $p['client'] !== '' || $p['category'] !== '' || $p['services'] || $p['technologies'] || $p['video'] || $p['url'];
$hasText = (bool)$p['case_study'];
?>
<section class="page-hero theme-deep">
  <div class="wrap page-hero__inner">
    <p class="crumb"><a href="/work">← All work</a></p>
    <?php if ($p['category'] !== ''): ?><p class="eyebrow"><?= e($p['category']) ?></p><?php endif ?>
    <h1 class="page-hero__title"><?= e($p['name']) ?></h1>
    <?php if ($p['description'] !== '' || !empty($p['placeholder'])): ?><p class="lede"><?= e($p['description']) ?><?php if (!empty($p['placeholder'])): ?> <span class="ph-tag">Placeholder</span><?php endif ?></p><?php endif ?>
  </div>
</section>
<section class="theme-soft project">
  <div class="wrap">
    <?php if ($p['image']): ?>
    <figure class="project__cover ph" style="aspect-ratio:<?= img_ratio($p['image_w'], $p['image_h'], 1.2, 2.4, '16 / 9') ?>"><?= kv_img($p['image'], $p['image_w'], $p['image_h'], $p['image_alt'] !== '' ? $p['image_alt'] : $p['name'], 'eager') ?></figure>
    <?php endif ?>
    <?php if ($hasFacts || $hasText): ?>
    <div class="grid project__facts">
      <?php if ($hasFacts): ?>
      <dl class="facts" style="--m:4;--d:4">
        <?php if ($p['client'] !== ''): ?><div><dt>Client</dt><dd><?= e($p['client']) ?></dd></div><?php endif ?>
        <?php if ($p['category'] !== ''): ?><div><dt>Type</dt><dd><?= e($p['category']) ?></dd></div><?php endif ?>
        <?php if ($p['services']): ?><div><dt>Klyval's role</dt><dd><?= e(implode(', ', $p['services'])) ?></dd></div><?php endif ?>
        <?php if ($p['technologies']): ?><div><dt>Technology</dt><dd><?= e(implode(', ', $p['technologies'])) ?></dd></div><?php endif ?>
        <?php if ($p['video']): ?><div><dt>Video</dt><dd><a href="<?= e($p['video']) ?>" target="_blank" rel="noopener" data-cursor="OPEN ↗">Watch ↗</a></dd></div><?php endif ?>
        <?php if ($p['url']): ?><div><dt>Live project</dt><dd><a href="<?= e($p['url']) ?>" target="_blank" rel="noopener" data-cursor="OPEN ↗">Visit project ↗</a></dd></div><?php endif ?>
      </dl>
      <?php endif ?>
      <?php if ($hasText): ?>
      <div class="prose" style="--m:4;--d:<?= $hasFacts ? 7 : 8 ?>;--s:<?= $hasFacts ? 6 : 3 ?>">
        <h2 class="eyebrow">Overview</h2>
        <?php foreach ($p['case_study'] as $para): ?><p><?= nl2br(e($para)) ?></p><?php endforeach ?>
      </div>
      <?php endif ?>
    </div>
    <?php endif ?>
    <?php if ($p['gallery_items']): ?>
    <ul class="gallery" role="list" aria-label="Project images">
      <?php foreach ($p['gallery_items'] as $g): ?>
      <li><figure><span class="ph" style="aspect-ratio:<?= img_ratio($g['w'], $g['h'], .75, 2.4, '4 / 3') ?>"><?= kv_img($g['src'], $g['w'], $g['h'], $g['alt'] !== '' ? $g['alt'] : $p['name']) ?></span><?php if ($g['caption'] !== ''): ?><figcaption><?= e($g['caption']) ?></figcaption><?php endif ?></figure></li>
      <?php endforeach ?>
    </ul>
    <?php endif ?>
    <?php if ($team): ?>
    <div class="project__team">
      <h2 class="eyebrow">Team</h2>
      <ul class="credits" role="list">
        <?php foreach ($team as $m): ?>
        <li><a class="credit" href="/team/<?= e($m['slug']) ?>" data-cursor="PROFILE">
          <span class="credit__portrait ph"><?php if (!empty($m['image'])): ?><?= kv_img($m['image'], 0, 0, '') ?><?php endif ?></span>
          <span class="credit__text"><span class="credit__name"><?= e($m['name']) ?></span><span class="credit__role"><?= e($p['team_roles'][$m['slug']] ?? $m['role']) ?></span></span>
        </a></li>
        <?php endforeach ?>
      </ul>
    </div>
    <?php endif ?>
  </div>
</section>
<?php if ($next): ?>
<a class="next-project theme-deep" href="/work/<?= e($next['slug']) ?>" data-cursor="VIEW">
  <span class="wrap next-project__inner">
    <span><span class="next-project__label">Next project</span><span class="next-project__name"><?= e($next['name']) ?></span></span>
    <span class="next-project__arrow" aria-hidden="true">→</span>
  </span>
</a>
<?php endif ?>
<?php View::section('pages/home/cta', ['copy' => $copy]) ?>
