<?php $isNew = !$id; $act = $isNew ? '/admin/team/create' : "/admin/team/$id/save"; $E = $errors; ?>
<div class="page-head"><h1 class="h2"><?= e($title) ?></h1><?php if (!$isNew && !empty($v['is_active'])): ?><a class="btn btn--ghost btn--sm" href="/team/<?= e($v['slug']) ?>" target="_blank" rel="noopener">View ↗</a><?php endif ?></div>
<?php if (isset($E['form'])): ?><p class="flash flash--err" role="alert"><?= e($E['form']) ?></p><?php endif ?>
<?php if ($E): ?><p class="flash flash--err" role="alert">Please fix the highlighted fields.</p><?php endif ?>
<form class="form-admin" method="post" action="<?= e($act) ?>" novalidate>
  <?= csrf_field() ?>
  <fieldset class="panel"><legend>Profile</legend>
    <?= fld('name', 'Name', $v['name'] ?? '', $E, ['required' => true, 'max' => 120]) ?>
    <?= fld('slug', 'Address (URL)', $v['slug'] ?? '', $E, ['max' => 120, 'hint' => 'Leave blank to generate it from the name.']) ?>
    <?php if (!$isNew && !empty($v['is_active'])): ?><p class="hint warn">Changing the address of a live profile changes its public URL. The old address will redirect to the new one.</p><?php endif ?>
    <?= fld('role', 'Role', $v['role'] ?? '', $E, ['required' => true, 'max' => 120]) ?>
    <?= fmedia('portrait_media_id', 'Portrait', $media, isset($v['portrait_media_id']) ? (int)$v['portrait_media_id'] : null, $E) ?>
    <?= fld('short_bio', 'Short bio', $v['short_bio'] ?? '', $E, ['type' => 'textarea', 'rows' => 3, 'max' => 280, 'hint' => 'Up to 280 characters.']) ?>
    <?= fld('full_bio', 'Full bio', $v['full_bio'] ?? '', $E, ['type' => 'textarea', 'rows' => 10, 'max' => 20000, 'hint' => 'Plain text. A blank line starts a new paragraph. No HTML.']) ?>
  </fieldset>
  <fieldset class="panel"><legend>Skills, links and projects</legend>
    <?= fld('skills', 'Skills', $rel['skills'] ?? '', $E, ['max' => 600, 'hint' => 'Comma separated. These are people\'s skills, not project technologies.']) ?>
    <?= fld('portfolio_url', 'Portfolio link', $v['portfolio_url'] ?? '', $E, ['type' => 'url', 'max' => 500]) ?>
    <fieldset class="sub"><legend>Social links</legend>
      <?php foreach (Taxonomy::PLATFORMS as $p => $label): ?><?= fld("social_url[$p]", $label, $rel['socials'][$p] ?? '', ['social_url[' . $p . ']' => $E["social_$p"] ?? ''] + [], ['type' => 'url', 'max' => 500]) ?><?php endforeach ?>
    </fieldset>
    <fieldset class="sub"><legend>Projects this person worked on</legend>
      <?php if (!$projects): ?><p class="hint">No projects yet.</p><?php endif ?>
      <?php foreach ($projects as $p): ?><?= fcheck('project_ids[]', $p['name'] . ' (' . (Taxonomy::PROJECT_STATUSES[$p['status']] ?? $p['status']) . ')', in_array((int)$p['id'], $rel['projects'], true), '', (string)(int)$p['id']) ?><?php endforeach ?>
    </fieldset>
  </fieldset>
  <fieldset class="panel"><legend>Visibility</legend>
    <?= fcheck('is_active', 'Active (shown on the site)', !empty($v['is_active']), 'Inactive profiles are hidden but never lost.') ?>
    <?= fcheck('featured', 'Featured on the homepage', !empty($v['featured'])) ?>
    <?= fld('display_order', 'Display order', (string)($v['display_order'] ?? 0), $E, ['type' => 'number', 'hint' => 'Lower numbers first. Ties are broken by creation order.']) ?>
    <?= fcheck('is_placeholder', 'This is placeholder content', !empty($v['is_placeholder'])) ?>
  </fieldset>
  <div class="actions"><button class="btn btn--primary" type="submit"><span class="btn__label">Save profile</span></button><a href="/admin/team">Cancel</a></div>
</form>
<?php if (!$isNew && empty($v['is_active']) && ($_SESSION['admin_role'] ?? '') === 'admin'): ?>
<section class="panel danger-zone" aria-label="Delete">
  <h2 class="h3">Delete permanently</h2>
  <form method="post" action="/admin/team/<?= (int)$id ?>/delete" class="delete-form"><?= csrf_field() ?>
    <p class="hint warn">Permanent. This cannot be undone. Type the address <code><?= e($v['slug']) ?></code> to confirm.</p>
    <label class="sr-only" for="confirm">Type the address to confirm</label><input id="confirm" name="confirm" autocomplete="off" required><button class="link-danger" type="submit">Delete permanently</button></form>
</section>
<?php endif ?>
