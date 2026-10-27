<?php
/* The ONE place the identity is drawn. A logo uploaded in Settings replaces the built-in placeholder mark.
   The final KLYVAL identity can be swapped here (or by uploading it) without touching anything else. */
$logoMedia = Settings::media('logo');
?>
<span class="logo">
<?php if ($logoMedia): ?>
  <img class="logo__img" src="<?= e(media_url($logoMedia)) ?>" alt="Klyval" height="26" style="height:26px;width:auto">
<?php else: ?>
  <svg class="logo__mark" viewBox="0 0 32 32" width="26" height="26" aria-hidden="true" focusable="false"><path d="M7 4v24M26 4 11 16l15 12" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="square"/></svg>
  <span class="logo__word">KLYVAL</span>
<?php endif ?>
</span>
