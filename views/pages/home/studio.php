<section class="studio theme-soft" aria-labelledby="studio-title">
  <div class="wrap studio__grid">
    <div>
      <p class="eyebrow">The studio</p>
      <h2 id="studio-title" class="h2 studio__title"><?= e($copy['studio_title']) ?></h2>
    </div>
    <div class="studio__body">
      <p class="lede"><?= e($copy['studio_text']) ?></p>
      <p class="studio__note">Build, Create and Launch happen in one place, so what we make and how it reaches people are decided together.</p>
      <?php View::partial('button', ['label' => 'Meet the studio', 'href' => '/studio', 'variant' => 'ghost']) ?>
    </div>
  </div>
</section>
