<h1 class="h2">Dashboard</h1>
<?php if ($maintenance): ?><p class="flash flash--err">Maintenance mode is ON. Visitors see a "Back soon" page. <?php if (($_SESSION['admin_role'] ?? '') === 'admin'): ?><a href="/admin/settings">Change in Settings</a><?php endif ?></p><?php endif ?>
<?php if (!empty($stray)): ?><p class="flash flash--err" role="alert">Unexpected files in the uploads folder: <?= e(implode(', ', array_slice($stray, 0, 5))) ?>. Only images created by Klyval belong there. Investigate and remove them, and run <code>php bin/check-uploads.php</code>.</p><?php endif ?>
<?php if (empty($uploadsWritable)): ?><p class="flash flash--err" role="alert">The uploads folder is not writable, so image uploads will fail.</p><?php endif ?>
<?php if ($placeholders > 0): ?><p class="flash">There are <?= (int)$placeholders ?> records flagged as placeholders. They show a "Placeholder" tag on the public site until you unflag or remove them.</p><?php endif ?>
<section class="cards" aria-label="Overview">
  <a class="card" href="/admin/projects"><span class="card__n"><?= $projects['published'] ?></span><span>Published projects</span><small><?= $projects['draft'] ?> draft · <?= $projects['archived'] ?> archived</small></a>
  <a class="card" href="/admin/team"><span class="card__n"><?= $team ?></span><span>Active team members</span></a>
  <a class="card" href="/admin/services"><span class="card__n"><?= $services ?></span><span>Active services</span></a>
  <a class="card" href="/admin/media"><span class="card__n"><?= $media ?></span><span>Images</span></a>
</section>
<h2 class="h3">Enquiries</h2>
<p class="pills"><?php foreach (Taxonomy::ENQUIRY_STATUSES as $k => $label): ?><a href="/admin/enquiries?status=<?= e($k) ?>" class="pill pill--link"><?= e($label) ?> <b><?= $enq[$k] ?></b></a> <?php endforeach ?></p>
<?php if ($recent): ?>
<div class="table-wrap"><table class="table"><caption class="sr-only">Latest enquiries</caption>
  <thead><tr><th>Date</th><th>Name</th><th>Type</th><th>Status</th></tr></thead>
  <tbody><?php foreach ($recent as $r): ?><tr><td><?= e(substr($r['created_at'], 0, 10)) ?></td><td><a href="/admin/enquiries/<?= (int)$r['id'] ?>"><?= e($r['name']) ?></a><?= $r['company'] ? ' · ' . e($r['company']) : '' ?></td><td><?= e($r['project_type']) ?></td><td><?= admin_pill(Taxonomy::ENQUIRY_STATUSES[$r['status']] ?? $r['status'], $r['status'] === 'new' ? 'new' : '') ?></td></tr><?php endforeach ?></tbody></table></div>
<?php else: ?><p class="muted">No enquiries yet.</p><?php endif ?>
