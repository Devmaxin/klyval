<?php $isNew = !$id; $act = $isNew ? '/admin/projects/create' : "/admin/projects/$id/save"; $E = $errors; ?>
<div class="page-head"><h1 class="h2"><?= e($title) ?></h1><?php if (!$isNew && ($v['status'] ?? '') === 'published'): ?><a class="btn btn--ghost btn--sm" href="/work/<?= e($v['slug']) ?>" target="_blank" rel="noopener">View ↗</a><?php endif ?></div>
<?php if (isset($E['form'])): ?><p class="flash flash--err" role="alert"><?= e($E['form']) ?></p><?php endif ?>
<?php if ($E): ?><p class="flash flash--err" role="alert">Please fix the highlighted fields.</p><?php endif ?>
<form class="form-admin" method="post" action="<?= e($act) ?>" novalidate>
  <?= csrf_field() ?>
  <fieldset class="panel"><legend>Basics</legend>
    <?= fld('name', 'Project name', $v['name'] ?? '', $E, ['required' => true, 'max' => 160]) ?>
    <?= fld('slug', 'Address (URL)', $v['slug'] ?? '', $E, ['max' => 120, 'hint' => 'Leave blank to generate it from the name. Lowercase letters, numbers and hyphens.']) ?>
    <?php if (!$isNew && ($v['status'] ?? 'draft') !== 'draft'): ?><p class="hint warn">Changing the address of a live project changes its public URL. The old address will redirect to the new one.</p><?php endif ?>
    <?= fld('client_name', 'Client', $v['client_name'] ?? '', $E, ['max' => 160]) ?>
    <?= fselect('kind', 'Category', Taxonomy::KINDS, $v['kind'] ?? 'client', $E) ?>
    <?= fld('description', 'Short description', $v['description'] ?? '', $E, ['type' => 'textarea', 'rows' => 3, 'max' => 2000]) ?>
  </fieldset>
  <fieldset class="panel"><legend>Case study</legend>
    <?= fld('case_study', 'Case study', $v['case_study'] ?? '', $E, ['type' => 'textarea', 'rows' => 12, 'max' => 20000, 'hint' => 'Plain text. A blank line starts a new paragraph. No HTML.']) ?>
  </fieldset>
  <fieldset class="panel"><legend>Visuals</legend>
    <?= fmedia('cover_media_id', 'Cover image', $media, isset($v['cover_media_id']) ? (int)$v['cover_media_id'] : null, $E) ?>
    <?= fld('video_url', 'Video link', $v['video_url'] ?? '', $E, ['type' => 'url', 'max' => 500, 'hint' => 'An https link (YouTube, Vimeo and similar).']) ?>
    <?= fld('project_url', 'Project link', $v['project_url'] ?? '', $E, ['type' => 'url', 'max' => 500]) ?>
    <fieldset class="sub"><legend>Gallery</legend>
      <?php if (!$media): ?><p class="hint">No images yet. Upload some in Media first.</p><?php endif ?>
      <div class="media-pick__grid">
      <?php foreach ($media as $m): $g = $rel['gallery'][$m['id']] ?? null; ?>
        <div class="gallery-pick"><label><input type="checkbox" name="gallery_ids[]" value="<?= (int)$m['id'] ?>"<?= $g ? ' checked' : '' ?>><img src="/uploads/<?= e(rawurlencode($m['filename'])) ?>" alt="<?= e((string)$m['alt_text']) ?>" loading="lazy" width="96" height="96"></label>
          <input type="number" min="0" name="gallery_order[<?= (int)$m['id'] ?>]" value="<?= (int)($g['display_order'] ?? 0) ?>" aria-label="Gallery order"><input name="gallery_caption[<?= (int)$m['id'] ?>]" value="<?= e((string)($g['caption'] ?? '')) ?>" maxlength="250" placeholder="Caption" aria-label="Caption"></div>
      <?php endforeach ?></div>
    </fieldset>
  </fieldset>
  <fieldset class="panel"><legend>Services, technology and team</legend>
    <fieldset class="sub"><legend>Services delivered</legend>
      <?php if (!$services): ?><p class="hint">No active services yet. Add them in Services.</p><?php endif ?>
      <?php foreach ($services as $s): ?><?= fcheck('service_ids[]', Taxonomy::PILLARS[$s['pillar']]['label'] . ' — ' . $s['name'], in_array((int)$s['id'], $rel['services'], true), '', (string)(int)$s['id']) ?><?php endforeach ?>
    </fieldset>
    <?= fld('technologies', 'Technologies', $rel['technologies'] ?? '', $E, ['max' => 600, 'hint' => 'Comma separated, for example: PHP, MySQL, Figma. These are project technologies, not people\'s skills.']) ?>
    <fieldset class="sub"><legend>Team on this project</legend>
      <?php if (!$team): ?><p class="hint">No active team members yet.</p><?php endif ?>
      <?php foreach ($team as $m): $t = $rel['team'][$m['id']] ?? null; ?>
        <div class="row-pick"><label><input type="checkbox" name="team_ids[]" value="<?= (int)$m['id'] ?>"<?= $t ? ' checked' : '' ?>> <span><?= e($m['name']) ?> <span class="muted">· <?= e($m['role']) ?></span></span></label>
          <input name="team_role[<?= (int)$m['id'] ?>]" value="<?= e((string)($t['role_label'] ?? '')) ?>" maxlength="120" placeholder="Role on this project" aria-label="Role on this project"><input type="number" min="0" name="team_order[<?= (int)$m['id'] ?>]" value="<?= (int)($t['display_order'] ?? 0) ?>" aria-label="Order"></div>
      <?php endforeach ?>
    </fieldset>
  </fieldset>
  <fieldset class="panel"><legend>Publishing</legend>
    <?= fselect('status', 'Status', Taxonomy::PROJECT_STATUSES, $v['status'] ?? 'draft', $E, 'Only published projects appear on the site. Archived projects are hidden but never lost.') ?>
    <?= fcheck('featured', 'Featured on the homepage', !empty($v['featured'])) ?>
    <?= fld('display_order', 'Display order', (string)($v['display_order'] ?? 0), $E, ['type' => 'number', 'hint' => 'Lower numbers first. Ties are broken by creation order.']) ?>
    <?= fcheck('is_placeholder', 'This is placeholder content', !empty($v['is_placeholder']), 'Shows a "Placeholder" tag on the public site.') ?>
  </fieldset>
  <fieldset class="panel"><legend>Search engines</legend>
    <?= fld('seo_title', 'SEO title', $v['seo_title'] ?? '', $E, ['max' => 160]) ?>
    <?= fld('seo_description', 'SEO description', $v['seo_description'] ?? '', $E, ['max' => 255]) ?>
  </fieldset>
  <div class="actions"><button class="btn btn--primary" type="submit"><span class="btn__label">Save project</span></button><a href="/admin/projects">Cancel</a></div>
</form>
<?php if (!$isNew): ?>
<section class="panel danger-zone" aria-label="Archive and delete">
  <h2 class="h3">Archive or delete</h2>
  <?php if (($v['status'] ?? '') !== 'archived'): ?>
    <form method="post" action="/admin/projects/<?= (int)$id ?>/status"><?= csrf_field() ?><input type="hidden" name="to" value="archived"><p class="hint">Archiving hides the project from the site. It can be restored at any time.</p><button class="btn btn--ghost btn--sm" type="submit">Archive project</button></form>
  <?php else: ?>
    <form method="post" action="/admin/projects/<?= (int)$id ?>/status"><?= csrf_field() ?><input type="hidden" name="to" value="draft"><button class="btn btn--ghost btn--sm" type="submit">Restore as draft</button></form>
    <?php if (($_SESSION['admin_role'] ?? '') === 'admin'): ?>
    <form method="post" action="/admin/projects/<?= (int)$id ?>/delete" class="delete-form"><?= csrf_field() ?>
      <p class="hint warn">Permanent. This cannot be undone. Type the address <code><?= e($v['slug']) ?></code> to confirm.</p>
      <label class="sr-only" for="confirm">Type the address to confirm</label><input id="confirm" name="confirm" autocomplete="off" required><button class="link-danger" type="submit">Delete permanently</button></form>
    <?php endif ?>
  <?php endif ?>
</section>
<?php endif ?>
