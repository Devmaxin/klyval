<?php $isNew = !$id; $act = $isNew ? '/admin/insights/create' : "/admin/insights/$id/save"; $E = $errors;
$pubVal = !empty($v['published_at']) ? str_replace(' ', 'T', substr((string)$v['published_at'], 0, 16)) : ''; ?>
<div class="page-head"><h1 class="h2"><?= e($title) ?></h1></div>
<?php if (isset($E['form'])): ?><p class="flash flash--err" role="alert"><?= e($E['form']) ?></p><?php endif ?>
<?php if ($E): ?><p class="flash flash--err" role="alert">Please fix the highlighted fields.</p><?php endif ?>
<form class="form-admin" method="post" action="<?= e($act) ?>" novalidate>
  <?= csrf_field() ?>
  <fieldset class="panel"><legend>Article</legend>
    <?= fld('title', 'Title', $v['title'] ?? '', $E, ['required' => true, 'max' => 200]) ?>
    <?= fld('slug', 'Address (URL)', $v['slug'] ?? '', $E, ['max' => 120, 'hint' => 'Leave blank to generate it from the title.']) ?>
    <?php if (!$isNew && ($v['status'] ?? 'draft') !== 'draft'): ?><p class="hint warn">Changing the address of a live article changes its public URL. The old address will redirect to the new one.</p><?php endif ?>
    <?= fselect('category', 'Category', Taxonomy::ARTICLE_CATEGORIES, $v['category'] ?? 'studio', $E) ?>
    <?= fld('excerpt', 'Summary', $v['excerpt'] ?? '', $E, ['type' => 'textarea', 'rows' => 3, 'max' => 300, 'hint' => 'Shown in the list and used as the default description.']) ?>
    <?= fld('content', 'Article', $v['content'] ?? '', $E, ['type' => 'textarea', 'rows' => 16, 'max' => 60000, 'hint' => 'Plain text. A blank line starts a new paragraph. No HTML.']) ?>
    <?= fmedia('cover_media_id', 'Cover image', $media, isset($v['cover_media_id']) ? (int)$v['cover_media_id'] : null, $E) ?>
    <?php $opts = ['' => 'No author'] + array_column($authors, 'name', 'id'); ?>
    <?= fselect('author_id', 'Author', $opts, (string)($v['author_id'] ?? ''), $E) ?>
  </fieldset>
  <fieldset class="panel"><legend>Publishing</legend>
    <?= fselect('status', 'Status', Taxonomy::PROJECT_STATUSES, $v['status'] ?? 'draft', $E, 'Only published articles appear. A future publish date keeps it hidden until then.') ?>
    <?= fld('published_at', 'Publish date', $pubVal, $E, ['type' => 'datetime-local', 'hint' => 'Leave blank to use the moment you publish.']) ?>
    <?= fld('seo_title', 'SEO title', $v['seo_title'] ?? '', $E, ['max' => 160]) ?>
    <?= fld('seo_description', 'SEO description', $v['seo_description'] ?? '', $E, ['max' => 255]) ?>
  </fieldset>
  <div class="actions"><button class="btn btn--primary" type="submit"><span class="btn__label">Save article</span></button><a href="/admin/insights">Cancel</a></div>
</form>
<?php if (!$isNew): ?>
<section class="panel danger-zone" aria-label="Archive and delete"><h2 class="h3">Archive or delete</h2>
  <?php if (($v['status'] ?? '') !== 'archived'): ?>
    <form method="post" action="/admin/insights/<?= (int)$id ?>/status"><?= csrf_field() ?><input type="hidden" name="to" value="archived"><p class="hint">Archiving hides the article. It can be restored at any time.</p><button class="btn btn--ghost btn--sm" type="submit">Archive article</button></form>
  <?php else: ?>
    <form method="post" action="/admin/insights/<?= (int)$id ?>/status"><?= csrf_field() ?><input type="hidden" name="to" value="draft"><button class="btn btn--ghost btn--sm" type="submit">Restore as draft</button></form>
    <?php if (($_SESSION['admin_role'] ?? '') === 'admin'): ?>
    <form method="post" action="/admin/insights/<?= (int)$id ?>/delete" class="delete-form"><?= csrf_field() ?><p class="hint warn">Permanent. Type the address <code><?= e($v['slug']) ?></code> to confirm.</p><label class="sr-only" for="confirm">Type the address to confirm</label><input id="confirm" name="confirm" autocomplete="off" required><button class="link-danger" type="submit">Delete permanently</button></form>
    <?php endif ?>
  <?php endif ?>
</section>
<?php endif ?>
