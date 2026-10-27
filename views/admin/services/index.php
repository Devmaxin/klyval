<div class="page-head"><h1 class="h2">Services</h1><a class="btn btn--primary btn--sm" href="/admin/services/new"><span class="btn__label">New service</span></a></div>
<p class="filters-admin"><?php foreach (['' => 'All', 'active' => 'Active', 'inactive' => 'Inactive'] as $k => $l): ?><a href="/admin/services<?= $k ? '?state=' . e($k) : '' ?>"<?= $state === (string)$k ? ' aria-current="true"' : '' ?>><?= e($l) ?></a><?php endforeach ?></p>
<?php if (!$rows): ?><p class="muted">No services here yet.</p><?php else: ?>
<div class="table-wrap"><table class="table"><caption class="sr-only">Services</caption>
<thead><tr><th>Service</th><th>Group</th><th>Status</th><th>Features</th><th>Order</th><th><span class="sr-only">Actions</span></th></tr></thead><tbody>
<?php foreach ($rows as $r): ?>
<tr><td><a href="/admin/services/<?= (int)$r['id'] ?>/edit"><?= e($r['name']) ?></a> <?= $r['show_on_home'] ? admin_pill('On homepage', 'new') : '' ?><?= $r['is_placeholder'] ? admin_pill('Placeholder') : '' ?></td>
<td><?= e(Taxonomy::PILLARS[$r['pillar']]['label'] ?? '') ?></td><td><?= admin_pill($r['is_active'] ? 'Active' : 'Inactive', $r['is_active'] ? 'ok' : '') ?></td><td><?= (int)$r['feature_count'] ?></td><td><?= (int)$r['display_order'] ?></td>
<td><form method="post" action="/admin/services/<?= (int)$r['id'] ?>/active"><?= csrf_field() ?><input type="hidden" name="to" value="<?= $r['is_active'] ? '0' : '1' ?>"><button class="btn btn--ghost btn--sm" type="submit"><?= $r['is_active'] ? 'Make inactive' : 'Make active' ?></button></form></td></tr>
<?php endforeach ?></tbody></table></div><?php endif ?>
