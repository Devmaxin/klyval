<section class="stats theme-deep" aria-label="Studio in numbers">
  <div class="grid wrap">
    <?php foreach ($stats as $st): ?>
    <p class="stat" style="--m:2;--d:3"><span class="stat__value"><?= e($st['value']) ?></span><span class="stat__label"><?= e($st['label']) ?></span></p>
    <?php endforeach ?>
  </div>
</section>
