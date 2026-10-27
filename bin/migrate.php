<?php
// Usage: php bin/migrate.php   — applies pending database/migrations/*.sql in order. Safe to re-run; never drops anything.
// Migration files: no semicolons inside comments or strings (statements are split on ';').
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
require dirname(__DIR__) . '/app/helpers/bootstrap.php';
echo implode("\n", Migrator::run()) . "\n";
