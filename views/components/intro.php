<?php // BUILD → CREATE → LAUNCH. Markup only; motion lives in intro.css, sequencing in intro.js. ?>
<div class="intro" data-intro data-stage="pre">
  <div class="intro__grid" aria-hidden="true"><?php for ($i = 0; $i < 12; $i++): ?><i style="--i:<?= $i ?>"></i><?php endfor ?></div>
  <div class="intro__brand"><?php View::partial('logo') ?></div>
  <ol class="intro__stages" aria-hidden="true"><li>Build</li><li>Create</li><li>Launch</li></ol>
  <div class="intro__block" aria-hidden="true"></div>
  <i class="intro__streak" style="--k:0" aria-hidden="true"></i><i class="intro__streak" style="--k:1" aria-hidden="true"></i><i class="intro__streak" style="--k:2" aria-hidden="true"></i>
  <div class="intro__words">
    <p class="intro__word" data-w="build"><span>WE <br class="br-m">BUILD.</span></p>
    <p class="intro__word" data-w="create"><span>WE <br class="br-m">CREATE.</span></p>
    <p class="intro__word" data-w="launch"><span>WE <br class="br-m">LAUNCH.</span></p>
  </div>
  <button class="intro__skip" type="button" data-intro-skip>Skip intro</button>
  <div class="intro__progress" aria-hidden="true"><i></i></div>
</div>
