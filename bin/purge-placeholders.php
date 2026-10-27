<?php
// Usage: php bin/purge-placeholders.php --yes
// Permanently removes every project, team member and service flagged is_placeholder. Media is never touched.
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
require dirname(__DIR__) . '/app/helpers/bootstrap.php';
$n = 0;
foreach (['projects', 'team_members', 'services'] as $t) $n += (int)Database::query("SELECT COUNT(*) FROM $t WHERE is_placeholder = 1")->fetchColumn();
if (!in_array('--yes', $argv, true)) exit("$n placeholder record(s) would be deleted. Re-run with --yes to confirm.\n");
foreach (['projects', 'team_members', 'services'] as $t) Database::query("DELETE FROM $t WHERE is_placeholder = 1");
echo "Deleted $n placeholder record(s).\n";
