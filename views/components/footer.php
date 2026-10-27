<?php $socials = Settings::socials(); $mail = setting('contact_email'); $phone = setting('phone'); $wa = preg_replace('/\D+/', '', (string)setting('whatsapp')); ?>
<footer class="site-footer theme-deep">
  <div class="grid wrap">
    <div class="site-footer__brand" style="--m:4;--d:5">
      <a href="/" aria-label="Klyval, home"><?php View::partial('logo') ?></a>
      <p class="site-footer__desc">Digital Studio. We build. We create. We launch.</p>
      <?php if ($ft = setting('footer_text')): ?><p class="site-footer__desc"><?= e($ft) ?></p><?php endif ?>
    </div>
    <nav aria-label="Footer" style="--m:2;--d:3;--s:7">
      <ul class="footer-list"><li><a href="/work">Work</a></li><li><a href="/studio">Studio</a></li><li><a href="/services">Services</a></li><?php if (SiteContent::hasArticles()): ?><li><a href="/insights">Insights</a></li><?php endif ?><li><a href="/contact">Contact</a></li></ul>
    </nav>
    <div style="--m:2;--d:3;--s:10">
      <ul class="footer-list">
        <?php if ($mail): ?><li><a href="mailto:<?= e($mail) ?>"><?= e($mail) ?></a></li><?php endif ?>
        <?php if ($phone): ?><li><a href="tel:<?= e(preg_replace('/[^\d+]/', '', $phone)) ?>"><?= e($phone) ?></a></li><?php endif ?>
        <?php if ($wa): ?><li><a href="https://wa.me/<?= e($wa) ?>" rel="noopener" target="_blank" data-cursor="OPEN ↗">WhatsApp</a></li><?php endif ?>
        <?php foreach ($socials as $s): ?><li><a href="<?= e($s['url']) ?>" rel="noopener" target="_blank" data-cursor="OPEN ↗"><?= e(Taxonomy::PLATFORMS[$s['platform']] ?? $s['platform']) ?></a></li><?php endforeach ?>
      </ul>
    </div>
  </div>
  <p class="wrap site-footer__copy">© <?= date('Y') ?> Klyval. All rights reserved.</p>
</footer>
