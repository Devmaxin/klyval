<?php $mp = $projects; ?>
<section class="page-hero theme-light member-hero">
  <div class="wrap member-hero__grid">
    <div class="member-hero__text">
      <p class="crumb"><a href="/studio#team">← Studio</a></p>
      <h1 class="page-hero__title"><?= e($m['name']) ?></h1>
      <p class="lede"><?= e($m['role']) ?><?php if (!empty($m['placeholder'])): ?> <span class="ph-tag">Placeholder</span><?php endif ?></p>
      <?php if ($m['short_bio'] !== ''): ?><p class="member-hero__intro"><?= e($m['short_bio']) ?></p><?php endif ?>
    </div>
    <?php if ($m['image']): ?><div class="member-hero__portrait ph" style="aspect-ratio:4/5"><?= kv_img($m['image'], 0, 0, $m['name'], 'eager') ?></div><?php endif ?>
  </div>
</section>
<?php if ($m['bio'] || $m['skills'] || $m['portfolio_url'] || $m['socials']): ?>
<section class="theme-soft member-body">
  <div class="grid wrap">
    <?php if ($m['bio']): ?><div class="prose" style="--m:4;--d:6"><?php foreach ($m['bio'] as $para): ?><p><?= nl2br(e($para)) ?></p><?php endforeach ?></div><?php endif ?>
    <?php if ($m['skills'] || $m['portfolio_url'] || $m['socials']): ?>
    <dl class="facts" style="--m:4;--d:4;--s:9">
      <?php if ($m['skills']): ?><div><dt>Skills</dt><dd><?= e(implode(', ', $m['skills'])) ?></dd></div><?php endif ?>
      <?php if ($m['portfolio_url']): ?><div><dt>Portfolio</dt><dd><a href="<?= e($m['portfolio_url']) ?>" target="_blank" rel="noopener" data-cursor="OPEN ↗">Portfolio ↗</a></dd></div><?php endif ?>
      <?php foreach ($m['socials'] as $label => $href): ?><div><dt><?= e($label) ?></dt><dd><a href="<?= e($href) ?>" target="_blank" rel="noopener" data-cursor="OPEN ↗"><?= e($label) ?> ↗</a></dd></div><?php endforeach ?>
    </dl>
    <?php endif ?>
  </div>
</section>
<?php endif ?>
<?php if ($mp): ?>
<section class="theme-light member-projects" aria-labelledby="mp-title">
  <div class="wrap">
    <h2 id="mp-title" class="h3">Selected projects</h2>
    <?php View::partial('project-strip', ['projects' => $mp, 'label' => 'Projects ' . $m['name'] . ' worked on']) ?>
  </div>
</section>
<?php endif ?>
<?php View::section('pages/home/cta', ['copy' => $copy]) ?>
