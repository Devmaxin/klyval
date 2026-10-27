<section class="services theme-light" id="services" aria-labelledby="services-title">
  <div class="wrap">
    <h2 id="services-title" class="sr-only">What we do</h2>
    <ul class="svc-list">
      <?php foreach ($services as $s): ?>
      <li id="<?= e($s['slug']) ?>">
        <a class="svc-row" href="/services#<?= e($s['slug']) ?>" data-cursor="DETAILS">
          <span class="svc-row__name"><?= e(strtoupper($s['name'])) ?></span>
          <p class="svc-row__text"><?= e($s['lead']) ?></p>
          <span class="svc-row__arrow" aria-hidden="true">→</span>
        </a>
      </li>
      <?php endforeach ?>
    </ul>
  </div>
</section>
