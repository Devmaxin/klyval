<section class="cta theme-deep" aria-labelledby="cta-title">
  <div class="wrap cta__inner">
    <h2 id="cta-title" class="h2 cta__title"><?= e($copy['cta_title']) ?></h2>
    <p class="lede"><?= e($copy['cta_text']) ?></p>
    <div class="magnetic" data-magnetic><?php View::partial('button', ['label' => 'Start a project', 'href' => '/contact', 'variant' => 'primary', 'arrow' => '↗']) ?></div>
  </div>
</section>
