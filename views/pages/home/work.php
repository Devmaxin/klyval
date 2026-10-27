<?php
$n = count($projects);
$live = $n >= 4;                                   // below this a loop would just repeat the same two or three images
$cols = [[], []];
foreach ($projects as $i => $p) $cols[$i % 2][] = [$i, $p];   // round-robin: every project appears exactly once, display order preserved
?>
<section class="work theme-deep" aria-labelledby="work-title">
  <div class="wrap">
    <div class="work__head">
      <h2 id="work-title" class="h2">Selected work</h2>
      <a class="btn btn--ghost" href="/work">View all work <span class="btn__arrow" aria-hidden="true">→</span></a>
    </div>
    <div class="wall"<?= $live ? ' data-live' : '' ?>>
      <?php foreach ($cols as $c => $items): if (!$items) continue; ?>
      <?php $loopAttrs = $live ? ' data-loop="y" data-speed="' . ($c ? 22 : 30) . '"' . ($c ? ' data-reverse' : '') : ''; // numbers and fixed words only ?>
      <div class="wall__col"<?= $loopAttrs ?>>
        <ul class="wall__track" role="list" data-track>
          <?php foreach ($items as [$i, $p]) View::partial('project-card', ['p' => $p, 'i' => $i, 'extra' => $i >= 6]) ?>
        </ul>
      </div>
      <?php endforeach ?>
    </div>
  </div>
</section>
