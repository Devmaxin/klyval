<section class="page-hero theme-deep">
  <div class="grid wrap page-hero__inner">
    <p class="crumb" style="--m:4;--d:12"><a href="/insights">← Insights</a></p>
    <h1 class="display page-hero__title" style="--m:4;--d:11"><?= e($a['title']) ?></h1>
    <p class="lede" style="--m:4;--d:7"><?= e($a['category']) ?> · <time datetime="<?= e(date('c', strtotime($a['published_at']))) ?>"><?= e(date('j F Y', strtotime($a['published_at']))) ?></time><?php if ($a['author']): ?> · <?php if ($a['author_slug']): ?><a href="/team/<?= e($a['author_slug']) ?>"><?= e($a['author']) ?></a><?php else: ?><?= e($a['author']) ?><?php endif ?><?php endif ?></p>
  </div>
</section>
<article class="theme-soft project">
  <div class="wrap">
    <?php if ($a['image']): ?><?php View::partial('visual', ['image' => $a['image'], 'ratio' => '16/8', 'alt' => $a['title']]) ?><?php endif ?>
    <div class="grid project__facts"><div class="prose" style="--m:4;--d:8;--s:3"><?php foreach ($a['content'] as $para): ?><p><?= nl2br(e($para)) ?></p><?php endforeach ?></div></div>
  </div>
</article>
<?php View::section('pages/home/cta', ['copy' => $copy]) ?>
