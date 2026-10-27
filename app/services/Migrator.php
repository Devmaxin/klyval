<?php
/** Applies pending database/migrations/*.sql in order. Safe to re-run; never drops anything. Statements are split on ';'. */
final class Migrator {
    /** @return list<string> human-readable log lines */
    public static function run(): array {
        $pdo = Database::pdo(); $log = [];
        $pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (version VARCHAR(80) PRIMARY KEY, applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB');
        $done = $pdo->query('SELECT version FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
        $files = glob(ROOT . '/database/migrations/*.sql') ?: []; sort($files);
        foreach ($files as $f) {
            $v = basename($f, '.sql');
            if (in_array($v, $done, true)) continue;
            foreach (array_filter(array_map('trim', explode(';', (string)file_get_contents($f)))) as $stmt) $pdo->exec($stmt);
            Database::query('INSERT INTO schema_migrations (version) VALUES (?)', [$v]);
            $log[] = "Applied $v";
        }
        $log[] = $log ? 'Database is up to date.' : 'Nothing to apply. Database is up to date.';
        return $log;
    }
}
