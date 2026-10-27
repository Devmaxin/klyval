<?php
View::partial('page-hero', ['title' => 'What we do', 'lede' => 'Three disciplines that work together, from the first build to launch day.', 'theme' => 'soft']);
$themes = ['build' => 'theme-light', 'create' => 'theme-soft', 'launch' => 'theme-deep'];
foreach ($services as $n => $s):
  $examples = [];
  foreach (($pillar_projects[$s['slug']] ?? []) as $slug) if (isset($project_index[$slug])) $examples[] = $project_index[$slug];
  $examples = array_slice($examples, 0, 3);
?>
<section class="pillar <?= $themes[$s['slug']] ?? 'theme-soft' ?>" id="<?= e($s['slug']) ?>" aria-labelledby="pillar-<?= e($s['slug']) ?>">
  <div class="wrap">
    <div class="grid pillar__head">
      <p class="pillar__num" style="--m:1;--d:1" aria-hidden="true"><?= str_pad((string)($n + 1), 2, '0', STR_PAD_LEFT) ?></p>
      <h2 id="pillar-<?= e($s['slug']) ?>" class="h2 pillar__name" style="--m:3;--d:4"><?= e($s['name']) ?></h2>
      <p class="lede pillar__lead" style="--m:4;--d:6;--s:7"><?= e($s['lead']) ?></p>
    </div>
    <?php if ($s['services']): ?>
    <ul class="offers" role="list">
      <?php foreach ($s['services'] as $sv): ?>
      <li class="offer">
        <h3 class="offer__name"><?= e($sv['name']) ?><?php if ($sv['placeholder']): ?> <span class="ph-tag">Placeholder</span><?php endif ?></h3>
        <div class="offer__body">
          <?php foreach ($sv['description'] as $para): ?><p><?= nl2br(e($para)) ?></p><?php endforeach ?>
          <?php if ($sv['features']): ?><ul class="offer__list"><?php foreach ($sv['features'] as $f): ?><li><?= e($f) ?></li><?php endforeach ?></ul><?php endif ?>
        </div>
      </li>
      <?php endforeach ?>
    </ul>
    <?php endif ?>
    <?php if ($examples): ?>
    <div class="pillar__work">
      <h3 class="eyebrow">Selected <?= e(strtolower($s['name'])) ?> work</h3>
      <ul class="work-grid work-grid--tight" role="list"><?php foreach ($examples as $p) View::partial('work-card', ['p' => $p]) ?></ul>
    </div>
    <?php endif ?>
  </div>
</section>
<?php endforeach ?>
<?php View::section('pages/home/cta', ['copy' => $copy]) ?>
