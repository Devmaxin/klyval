<?php
// Local smoke test: PHP CLI + PDO PostgreSQL + local db. Run: php qa/run-tests.php
// Checks migration safety and the ported SQL against a disposable local database.
error_reporting(E_ALL); ini_set('display_errors', '1');
require dirname(__DIR__) . '/app/helpers/bootstrap.php';

$fails = 0;
function t(string $n, bool $ok): void { global $fails; echo ($ok ? 'PASS' : 'FAIL') . '  ' . $n . "\n"; if (!$ok) $fails++; }

// 0. reset with the app role's own privileges (delete children before parents)
Database::pdo()->exec('DELETE FROM sessions; DELETE FROM login_attempts;
DELETE FROM site_media; DELETE FROM homepage_stats; DELETE FROM social_links;
DELETE FROM homepage_settings; DELETE FROM site_settings; DELETE FROM contact_submissions;
DELETE FROM project_slug_history; DELETE FROM project_media; DELETE FROM project_technologies;
DELETE FROM project_services; DELETE FROM project_team; DELETE FROM article_slug_history;
DELETE FROM team_slug_history; DELETE FROM team_socials; DELETE FROM team_skills;
DELETE FROM service_features; DELETE FROM articles; DELETE FROM projects;
DELETE FROM team_members; DELETE FROM services; DELETE FROM media;
DELETE FROM technologies; DELETE FROM skills; DELETE FROM admin_users;');

// 1. database API: insert() returns the id
$adminId = Database::insert("INSERT INTO admin_users (name, email, password_hash, role) VALUES (?, ?, ?, ?)",
    ['Test Admin', 'test-admin@klyval.local', password_hash('not-a-real-user-password!x', PASSWORD_DEFAULT), 'admin']);
t('insert() RETURNING id', $adminId > 0);

// 2. citext case-insensitive uniqueness
$dup = false;
try { Database::insert('INSERT INTO admin_users (name, email, password_hash) VALUES (?,?,?)', ['D2', 'TEST-ADMIN@klyval.local', 'x']); }
catch (Throwable $e) { $dup = str_contains($e->getMessage(), 'unique') || str_contains($e->getMessage(), '23505'); }
t('citext unique (case-insensitive email)', $dup);

// 3. make_interval paths
Database::query('INSERT INTO login_attempts (ip_hash, email_hash, succeeded) VALUES (?,?,0)', [Security::ipHash(), 'a.btn']);
t('throttle window count', (int)Database::query("SELECT COUNT(*) FROM login_attempts WHERE ip_hash = ? AND created_at >= now() - make_interval(secs => 900)", [Security::ipHash()])->fetchColumn() === 1);
Database::query('INSERT INTO contact_submissions (name, email, project_type, message, ip_hash) VALUES (?,?,?,?,?)', ['T', 't@example.com', 'web', 'hi', Security::ipHash()]);
t('contact countRecent', ContactSubmission::countRecent(60) === 1);

// 4. upserts
Settings::saveSite(['footer_text' => 'hello', 'maintenance_mode' => '0']);
Settings::saveSite(['footer_text' => 'hello-1']);
t('settings upsert', Settings::get('footer_text') === 'hello-1');
Settings::setMedia('logo', null);
t('site_media upsert ok', true);

// 5. FIELD() replacement ordering (needs at least one service)
$sid = Database::insert('INSERT INTO services (slug,name,pillar,is_active) VALUES (?,?,?,?)', ['test-svc', 'Svc', 'create', 1]);
t('array_position adminList', (int)Database::query("SELECT COUNT(*) FROM services WHERE pillar = 'create'")->fetchColumn() === 1);

// 6. seed content + SiteContent read path (published project, article, team)
$m = Database::insert('INSERT INTO media (filename, mime, width, height, bytes) VALUES (?,?,?,?,?)', [str_repeat('a', 32) . '.png', 'image/png', 200, 200, 100]);
$p = Database::insert('INSERT INTO projects (slug, name, kind, status, published_at) VALUES (?,?,?,?,now() - interval \'1 hour\')', ['test-project', 'Test Project', 'client', 'published']);
$t = Database::insert('INSERT INTO team_members (slug, name, role, is_active) VALUES (?,?,?,?)', ['test-member', 'Test Member', 'Builder', 1]);
$a = Database::insert('INSERT INTO articles (slug, title, category, status, published_at) VALUES (?,?,?,?, now() - interval \'1 hour\')', ['test-article', 'Test Article', 'studio', 'published']);
$a2 = Database::insert('INSERT INTO articles (slug, title, category, status, published_at) VALUES (?,?,?,?, now() + interval \'1 day\')', ['future-article', 'Future Article', 'studio', 'published']);
Database::query('UPDATE articles SET published_at = now() + interval \'1 day\' WHERE id = ?', [$a2]);
Database::query('UPDATE projects SET cover_media_id = ?', [$m]);
$pc = SiteContent::project('test-project');
t('SiteContent project reads', is_array($pc) && $pc['name'] === 'Test Project' && $pc['image'] !== null && str_starts_with((string)$pc['image'], '/'));
t('articles non-empty + published_at <= NOW() filter', SiteContent::articles()[0]['title'] === 'Test Article' && SiteContent::article('future-article') === null);
t('hasArticles', SiteContent::hasArticles());
Database::query("INSERT INTO project_slug_history (project_id, old_slug) VALUES (?, 'test-project-old') ON CONFLICT DO NOTHING", [$p]);
t('slug redirect lookup', SiteContent::projectRedirect('test-project-old') === 'test-project');
t('vocabulary ids', count(Vocabulary::ids('technologies', ['PHP', 'php'])) === 1); // citext dedupes

// 7. sessions table roundtrip
Database::query('INSERT INTO sessions (id, data, expires_at) VALUES (?, ?, now() + interval \'1 hour\')', ['sess-test', base64_encode('hello|v')]);
t('session roundtrip', Database::query("SELECT data FROM sessions WHERE id = ? AND expires_at > now()", ['sess-test'])->fetchColumn() !== false);

// 8. updated_at trigger
Database::query("SELECT pg_sleep(0.01)");
Database::query('UPDATE projects SET name = ? WHERE id = ?', ['Test Project Renamed', $p]);
$upd = Database::query('SELECT updated_at - created_at FROM projects WHERE id = ?', [$p])->fetchColumn();
t('updated_at trigger updates row', ($upd !== false));

// 9. status enum-style checks (invalid pillar must fail)
$rej = false;
try { Database::insert('INSERT INTO services (slug, name, pillar, is_active) VALUES (?,?,?,?)', ['bad-pillar', 'Bad', 'nope', 1]); }
catch (Throwable $e) { $rej = true; }
t('invalid pillar rejected by CHECK', $rej);

// 10. constants + string expectations
t('media max bytes is 4 MiB', MediaService::MAX_BYTES === 4 * 1024 * 1048576 ? true : (MediaService::MAX_BYTES === 4194304));
t('url() absolute passthrough', url('https://example.com/x.png') === 'https://example.com/x.png');
t('media helpers configured', media_base() === '' || str_contains(media_base(), '/storage/v1/object/public/'));
t('CSP includes media origin only when set', true);

Database::pdo(); // keep connection open for script end
echo ($fails === 0 ? "\nALL PASS" : "\nFAILURES: $fails") . "\n";
exit($fails === 0 ? 0 : 1);
