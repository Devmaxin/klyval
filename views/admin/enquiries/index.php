<h1 class="h2">Enquiries</h1>
<p class="filters-admin"><a href="/admin/enquiries"<?= $status === '' ? ' aria-current="true"' : '' ?>>All</a><?php foreach (Taxonomy::ENQUIRY_STATUSES as $k => $l): ?><a href="/admin/enquiries?status=<?= e($k) ?>"<?= $status === $k ? ' aria-current="true"' : '' ?>><?= e($l) ?> (<?= $counts[$k] ?>)</a><?php endforeach ?></p>
<?php if (!$rows): ?><p class="muted">No enquiries here yet.</p><?php else: ?>
<div class="table-wrap"><table class="table"><caption class="sr-only">Enquiries</caption>
<thead><tr><th>Date</th><th>Name</th><th>Email</th><th>Type</th><th>Status</th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr><td><?= e(substr($r['created_at'], 0, 16)) ?></td><td><a href="/admin/enquiries/<?= (int)$r['id'] ?>"><?= e($r['name']) ?></a><?= $r['company'] ? ' <span class="muted">· ' . e($r['company']) . '</span>' : '' ?></td><td><?= e($r['email']) ?></td><td><?= e($r['project_type']) ?></td><td><?= admin_pill(Taxonomy::ENQUIRY_STATUSES[$r['status']] ?? $r['status'], $r['status'] === 'new' ? 'new' : '') ?></td></tr><?php endforeach ?>
</tbody></table></div><?php endif ?>
