<div class="page-head"><h1 class="h2">Projects</h1><a class="btn btn--primary btn--sm" href="/admin/projects/new"><span class="btn__label">New project</span></a></div>
<p class="filters-admin"><?php foreach (['' => 'All'] + Taxonomy::PROJECT_STATUSES as $k => $l): ?><a href="/admin/projects<?= $k ? '?status=' . e($k) : '' ?>"<?= $status === (string)$k ? ' aria-current="true"' : '' ?>><?= e($l) ?></a><?php endforeach ?></p>
<?php if (!$rows): ?><p class="muted">No projects here yet.</p><?php else: ?>
<div class="table-wrap"><table class="table"><caption class="sr-only">Projects</caption>
<thead><tr><th>Project</th><th>Category</th><th>Status</th><th>Order</th><th>Updated</th></tr></thead><tbody>
<?php foreach ($rows as $r): ?>
<tr><td><a href="/admin/projects/<?= (int)$r['id'] ?>/edit"><?= e($r['name']) ?></a><?= $r['client_name'] ? ' <span class="muted">· ' . e($r['client_name']) . '</span>' : '' ?>
  <?= $r['featured'] ? admin_pill('Featured', 'new') : '' ?><?= $r['is_placeholder'] ? admin_pill('Placeholder') : '' ?></td>
  <td><?= e(Taxonomy::KINDS[$r['kind']] ?? '') ?></td><td><?= admin_pill(Taxonomy::PROJECT_STATUSES[$r['status']] ?? $r['status'], $r['status'] === 'published' ? 'ok' : '') ?></td>
  <td><?= (int)$r['display_order'] ?></td><td><?= e(substr($r['updated_at'], 0, 10)) ?></td></tr>
<?php endforeach ?></tbody></table></div><?php endif ?>
