<p class="crumb-admin"><a href="/admin/enquiries">← Enquiries</a></p>
<h1 class="h2"><?= e($e['name']) ?></h1>
<dl class="facts-admin">
  <div><dt>Email</dt><dd><a href="mailto:<?= e($e['email']) ?>"><?= e($e['email']) ?></a></dd></div>
  <?php if ($e['company']): ?><div><dt>Company</dt><dd><?= e($e['company']) ?></dd></div><?php endif ?>
  <div><dt>Project type</dt><dd><?= e($e['project_type']) ?></dd></div>
  <?php if ($e['budget']): ?><div><dt>Budget</dt><dd><?= e($e['budget']) ?></dd></div><?php endif ?>
  <div><dt>Received</dt><dd><?= e($e['created_at']) ?></dd></div>
</dl>
<section class="panel"><h2 class="h3">Message</h2><?php foreach (paragraphs($e['message']) as $p): ?><p><?= nl2br(e($p)) ?></p><?php endforeach ?></section>
<form class="panel" method="post" action="/admin/enquiries/<?= (int)$e['id'] ?>/update">
  <?= csrf_field() ?>
  <h2 class="h3">Handling</h2>
  <?= fselect('status', 'Status', Taxonomy::ENQUIRY_STATUSES, $e['status']) ?>
  <?= fld('notes', 'Internal notes', $e['notes'] ?? '', [], ['type' => 'textarea', 'rows' => 5, 'max' => 5000, 'hint' => 'Only visible here.']) ?>
  <button class="btn btn--primary" type="submit"><span class="btn__label">Save</span></button>
</form>
