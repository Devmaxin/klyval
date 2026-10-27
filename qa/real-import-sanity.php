<?php
// Real-dump sanity: counts + FK orphans + public reads, plus a few content previews.
require dirname(__DIR__) . '/app/helpers/bootstrap.php';
$fails = 0;
function ck(string $n, bool $ok, string $d = ''): void { global $fails; printf("%s  %s%s\n", $ok ? 'PASS' : 'FAIL', $n, $d ? " — $d" : ''); if (!$ok) $fails++; }

foreach (['admin_users','media','projects','project_media','project_team','project_technologies','team_members','team_socials','technologies','contact_submissions'] as $t) {
    printf("  %-22s %3d\n", $t, (int)Database::query("select count(*) from \"$t\"")->fetchColumn());
}
ck('FK orphans none', (int)Database::query("select count(*) from projects p left join media m on m.id = p.cover_media_id where p.cover_media_id is not null and m.id is null")->fetchColumn() === 0);

$ps = Database::query('select id, slug, name, status, featured, published_at from projects order by display_order, id')->fetchAll();
foreach ($ps as $p) printf("  project %-20s %-9s %s\n", $p['slug'], $p['status'], $p['published_at'] ?? '-');
$a = Database::query('select slug, status from articles')->fetchAll();
printf("  articles: %d\n", count($a));
$aP = SiteContent::projects();
ck('SiteContent::projects counts published', count($aP) === count(array_filter($ps, fn($x) => $x['status'] === 'published')), count($aP));
foreach ($aP as $c) ck('project reads: ' . $c['slug'], $c['name'] !== '' && $c['image'] !== null);
$enq = Database::query('select count(*), max(created_at) from contact_submissions')->fetch();
printf("  enquiries: %d, latest %s\n", $enq['count'], $enq['max']);
echo ($fails === 0 ? "SANITY PASS\n" : "SANITY FAILURES: $fails\n");
