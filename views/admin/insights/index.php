<div class="page-head"><h1 class="h2">Insights</h1><a class="btn btn--primary btn--sm" href="/admin/insights/new"><span class="btn__label">New article</span></a></div>
<p class="filters-admin"><?php foreach (['' => 'All'] + Taxonomy::PROJECT_STATUSES as $k => $l): ?><a href="/admin/insights<?= $k ? '?status=' . e($k) : '' ?>"<?= $status === (string)$k ? ' aria-current="true"' : '' ?>><?= e($l) ?></a><?php endforeach ?></p>
<?php if (!$rows): ?><p class="muted">No articles here yet. Insights stay off the site until one is published.</p><?php else: ?>
<div class="table-wrap"><table class="table"><caption class="sr-only">Articles</caption>
<thead><tr><th>Title</th><th>Category</th><th>Status</th><th>Published</th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr><td><a href="/admin/insights/<?= (int)$r['id'] ?>/edit"><?= e($r['title']) ?></a></td><td><?= e(Taxonomy::ARTICLE_CATEGORIES[$r['category']] ?? '') ?></td><td><?= admin_pill(Taxonomy::PROJECT_STATUSES[$r['status']] ?? $r['status'], $r['status'] === 'published' ? 'ok' : '') ?></td><td><?= e((string)substr((string)$r['published_at'], 0, 16)) ?></td></tr><?php endforeach ?>
</tbody></table></div><?php endif ?>
