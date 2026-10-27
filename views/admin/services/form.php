<?php $isNew = !$id; $act = $isNew ? '/admin/services/create' : "/admin/services/$id/save"; $E = $errors; ?>
<div class="page-head"><h1 class="h2"><?= e($title) ?></h1></div>
<?php if (isset($E['form'])): ?><p class="flash flash--err" role="alert"><?= e($E['form']) ?></p><?php endif ?>
<?php if ($E): ?><p class="flash flash--err" role="alert">Please fix the highlighted fields.</p><?php endif ?>
<form class="form-admin" method="post" action="<?= e($act) ?>" novalidate>
  <?= csrf_field() ?>
  <fieldset class="panel"><legend>Service</legend>
    <?= fld('name', 'Name', $v['name'] ?? '', $E, ['required' => true, 'max' => 120]) ?>
    <?= fld('slug', 'Address', $v['slug'] ?? '', $E, ['max' => 120, 'hint' => 'Leave blank to generate it from the name.']) ?>
    <?= fselect('pillar', 'Group', array_map(fn($p) => 'We ' . strtolower($p['label']) . '.', Taxonomy::PILLARS), $v['pillar'] ?? 'build', $E, 'Build, Create and Launch are fixed by Klyval.') ?>
    <?= fld('description', 'Description', $v['description'] ?? '', $E, ['type' => 'textarea', 'rows' => 4, 'max' => 2000, 'hint' => 'Plain text. A blank line starts a new paragraph.']) ?>
    <?= fld('features', 'Features', $features, $E, ['type' => 'textarea', 'rows' => 6, 'hint' => 'One per line, up to 20.']) ?>
    <?= fmedia('visual_media_id', 'Visual', $media, isset($v['visual_media_id']) ? (int)$v['visual_media_id'] : null, $E) ?>
  </fieldset>
  <fieldset class="panel"><legend>Visibility</legend>
    <?= fcheck('is_active', 'Active (shown on the site)', !empty($v['is_active']), 'Inactive services are hidden but never lost.') ?>
    <?= fcheck('show_on_home', 'Show on the homepage', !empty($v['show_on_home'])) ?>
    <?= fld('display_order', 'Display order', (string)($v['display_order'] ?? 0), $E, ['type' => 'number', 'hint' => 'Lower numbers first. Ties are broken by creation order.']) ?>
    <?= fcheck('is_placeholder', 'This is placeholder content', !empty($v['is_placeholder'])) ?>
  </fieldset>
  <div class="actions"><button class="btn btn--primary" type="submit"><span class="btn__label">Save service</span></button><a href="/admin/services">Cancel</a></div>
</form>
<?php if (!$isNew && empty($v['is_active']) && ($_SESSION['admin_role'] ?? '') === 'admin'): ?>
<section class="panel danger-zone" aria-label="Delete">
  <h2 class="h3">Delete permanently</h2>
  <form method="post" action="/admin/services/<?= (int)$id ?>/delete" class="delete-form"><?= csrf_field() ?>
    <p class="hint warn">Permanent. This cannot be undone. Type the address <code><?= e($v['slug']) ?></code> to confirm.</p>
    <label class="sr-only" for="confirm">Type the address to confirm</label><input id="confirm" name="confirm" autocomplete="off" required><button class="link-danger" type="submit">Delete permanently</button></form>
</section>
<?php endif ?>
