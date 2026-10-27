<section class="hero theme-deep" aria-labelledby="hero-title">
  <div class="grid-lines" aria-hidden="true"></div>
  <div class="wrap hero__inner">
    <?php if (!empty($banner)): ?>
    <p class="hero__banner"><?php if ($banner['url']): ?><a href="<?= e($banner['url']) ?>"><?= e($banner['text']) ?> →</a><?php else: ?><?= e($banner['text']) ?><?php endif ?></p>
    <?php endif ?>
    <h1 id="hero-title" class="display hero__title" data-reveal>
      <span class="mask"><span>Good ideas</span></span>
      <span class="mask"><span>deserve good</span></span>
      <span class="mask"><span>execution.</span></span>
    </h1>
    <ul class="triad" aria-label="What we do">
      <?php foreach ($services as $s): ?><li><a href="#<?= e($s['slug']) ?>"><?= e($s['name']) ?></a></li><?php endforeach ?>
    </ul>
    <p class="lede hero__lede"><?= e($copy['hero_lede']) ?></p>
    <div class="hero__cta">
      <?php View::partial('button', ['label' => 'Start a project', 'href' => '/contact', 'variant' => 'primary', 'arrow' => '↗']) ?>
      <?php View::partial('button', ['label' => 'View our work', 'href' => '/work', 'variant' => 'ghost']) ?>
    </div>
  </div>
</section>
