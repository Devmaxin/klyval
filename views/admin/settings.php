<?php $E = $errors; ?>
<h1 class="h2">Settings</h1>
<?php if ($E): ?><p class="flash flash--err" role="alert">Please fix the highlighted fields.</p><?php endif ?>
<form class="form-admin" method="post" action="/admin/settings" novalidate>
  <?= csrf_field() ?>
  <fieldset class="panel"><legend>Contact details</legend>
    <?= fld('contact_email', 'Email', $v['contact_email'] ?? '', $E, ['type' => 'email', 'max' => 190]) ?>
    <?= fld('phone', 'Phone', $v['phone'] ?? '', $E, ['max' => 40]) ?>
    <?= fld('whatsapp', 'WhatsApp number', $v['whatsapp'] ?? '', $E, ['max' => 40, 'hint' => 'With country code, for example 234…']) ?>
    <?= fld('address', 'Address', $v['address'] ?? '', $E, ['type' => 'textarea', 'rows' => 3, 'max' => 300]) ?>
  </fieldset>
  <fieldset class="panel"><legend>Social links</legend>
    <?php foreach (Taxonomy::studioPlatforms() as $p => $label): $s = $socials[$p] ?? null; ?>
      <div class="row-pick"><?= fld("social_url[$p]", $label, $s['url'] ?? '', ["social_url[$p]" => $E["social_$p"] ?? ''], ['type' => 'url', 'max' => 500]) ?>
      <label><input type="checkbox" name="social_active[<?= e($p) ?>]" value="1"<?= (!$s || $s['is_active']) ? ' checked' : '' ?>> Show</label></div>
    <?php endforeach ?>
  </fieldset>
  <fieldset class="panel"><legend>Brand images</legend>
    <?= fmedia('media_logo', 'Logo', $media, $slots['logo'], $E) ?>
    <p class="hint">Leave empty to use the built-in placeholder mark. The final identity can be uploaded here later.</p>
    <?= fmedia('media_og_image', 'Social share image', $media, $slots['og_image'], $E) ?>
    <?= fmedia('media_favicon', 'Favicon', $media, $slots['favicon'], $E) ?>
  </fieldset>
  <fieldset class="panel"><legend>Search engines and footer</legend>
    <?= fld('seo_default_title', 'Default page title', $v['seo_default_title'] ?? '', $E, ['max' => 70]) ?>
    <?= fld('seo_default_description', 'Default description', $v['seo_default_description'] ?? '', $E, ['max' => 200]) ?>
    <?= fld('footer_text', 'Footer line', $v['footer_text'] ?? '', $E, ['max' => 200]) ?>
  </fieldset>
  <fieldset class="panel"><legend>Analytics</legend>
    <?= fselect('analytics_provider', 'Provider', ['none' => 'None', 'plausible' => 'Plausible', 'ga4' => 'Google Analytics 4'], $v['analytics_provider'] ?? 'none', $E, 'Klyval loads the provider script itself. Custom scripts cannot be added here.') ?>
    <?= fld('analytics_id', 'Domain (Plausible) or measurement ID (GA4)', $v['analytics_id'] ?? '', $E, ['max' => 60]) ?>
  </fieldset>
  <fieldset class="panel"><legend>Maintenance mode</legend>
    <?= fcheck('maintenance_mode', 'Show a "Back soon" page to visitors', ($v['maintenance_mode'] ?? '0') === '1', 'Signed-in administrators can still see the site.') ?>
    <?= fld('maintenance_message', 'Message', $v['maintenance_message'] ?? '', $E, ['type' => 'textarea', 'rows' => 3, 'max' => 300]) ?>
  </fieldset>
  <div class="actions"><button class="btn btn--primary" type="submit"><span class="btn__label">Save settings</span></button></div>
</form>
