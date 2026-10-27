<section class="proc theme-electric" data-process aria-labelledby="proc-title">
  <div class="grid wrap">
    <div class="proc__aside" style="--m:4;--d:5">
      <h2 id="proc-title" class="h2">How Klyval works</h2>
      <div class="proc__bar" aria-hidden="true"><i></i></div>
    </div>
    <ol class="proc__steps" style="--m:4;--d:6;--s:7">
      <?php foreach ($process as $i => $s): ?>
      <li class="proc__step" data-step>
        <span class="proc__num"><?= str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
        <h3 class="proc__name"><?= e($s['name']) ?></h3>
        <p class="proc__text"><?= e($s['text']) ?></p>
      </li>
      <?php endforeach ?>
    </ol>
  </div>
</section>
