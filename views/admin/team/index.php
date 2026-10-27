<div class="page-head"><h1 class="h2">Team</h1><a class="btn btn--primary btn--sm" href="/admin/team/new"><span class="btn__label">Add team member</span></a></div>
<p class="filters-admin"><?php foreach (['' => 'All', 'active' => 'Active', 'inactive' => 'Inactive'] as $k => $l): ?><a href="/admin/team<?= $k ? '?state=' . e($k) : '' ?>"<?= $state === (string)$k ? ' aria-current="true"' : '' ?>><?= e($l) ?></a><?php endforeach ?></p>
<?php if (!$rows): ?><p class="muted">No team members here yet.</p><?php else: ?>
<div class="table-wrap"><table class="table"><caption class="sr-only">Team</caption>
<thead><tr><th>Name</th><th>Role</th><th>Status</th><th>Order</th><th><span class="sr-only">Actions</span></th></tr></thead><tbody>
<?php foreach ($rows as $r): ?>
<tr><td><a href="/admin/team/<?= (int)$r['id'] ?>/edit"><?= e($r['name']) ?></a> <?= $r['featured'] ? admin_pill('Featured', 'new') : '' ?><?= $r['is_placeholder'] ? admin_pill('Placeholder') : '' ?></td>
<td><?= e($r['role']) ?></td><td><?= admin_pill($r['is_active'] ? 'Active' : 'Inactive', $r['is_active'] ? 'ok' : '') ?></td><td><?= (int)$r['display_order'] ?></td>
<td><form method="post" action="/admin/team/<?= (int)$r['id'] ?>/active"><?= csrf_field() ?><input type="hidden" name="to" value="<?= $r['is_active'] ? '0' : '1' ?>"><button class="btn btn--ghost btn--sm" type="submit"><?= $r['is_active'] ? 'Make inactive' : 'Make active' ?></button></form></td></tr>
<?php endforeach ?></tbody></table></div><?php endif ?>
