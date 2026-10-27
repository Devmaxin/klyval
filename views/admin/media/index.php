<h1 class="h2">Media</h1>
<form class="panel" method="post" action="/admin/media/upload" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <h2 class="h3">Upload an image</h2>
  <div class="field"><label for="f-image"><span>Image</span></label><input id="f-image" type="file" name="image" accept="image/jpeg,image/png,image/webp" required><small class="hint">JPEG, PNG or WebP, up to <?= (int)$maxMb ?> MB, up to 6000 px on a side. Images are re-encoded and scaled to 2400 px on upload.</small></div>
  <?= fld('alt_text', 'Description for screen readers', '', [], ['max' => 250, 'hint' => 'Say what the image shows. Leave blank for purely decorative images.']) ?>
  <button class="btn btn--primary" type="submit"><span class="btn__label">Upload</span></button>
</form>
<?php if (!$items): ?><p class="muted">No images yet.</p><?php else: ?>
<ul class="media-grid">
  <?php foreach ($items as $m): ?>
  <li class="media-card">
    <img src="<?= e(media_file_url($m['filename'])) ?>" alt="<?= e((string)$m['alt_text']) ?>" loading="lazy" width="<?= (int)$m['width'] ?>" height="<?= (int)$m['height'] ?>">
    <p class="hint"><?= (int)$m['width'] ?>×<?= (int)$m['height'] ?> · <?= round($m['bytes'] / 1024) ?> KB<?= $m['original_name'] ? ' · ' . e($m['original_name']) : '' ?></p>
    <form method="post" action="/admin/media/<?= (int)$m['id'] ?>/alt"><?= csrf_field() ?>
      <label class="sr-only" for="alt-<?= (int)$m['id'] ?>">Description</label>
      <input id="alt-<?= (int)$m['id'] ?>" name="alt_text" value="<?= e((string)$m['alt_text']) ?>" maxlength="250" placeholder="Description">
      <button class="btn btn--ghost btn--sm" type="submit">Save</button>
    </form>
    <?php if (($_SESSION['admin_role'] ?? '') === 'admin'): ?>
    <form method="post" action="/admin/media/<?= (int)$m['id'] ?>/delete" data-confirm="Delete this image? It will also be removed from any project galleries."><?= csrf_field() ?><button class="link-danger" type="submit">Delete</button></form>
    <?php endif ?>
  </li>
  <?php endforeach ?>
</ul>
<?php endif ?>
