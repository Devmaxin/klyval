<?php
final class Database {
    private static ?PDO $pdo = null;
    /** Application code may only touch tables in this list; dynamic identifiers are never taken from user input. */
    private const TABLES = [
        'admin_users','site_settings','homepage_settings','contact_submissions','media','technologies','skills',
        'projects','project_slug_history','project_media','project_technologies','team_members','team_slug_history',
        'team_socials','team_skills','project_team','services','service_features','project_services',
        'homepage_stats','social_links','site_media','articles','article_slug_history','sessions','login_attempts',
    ];

    private static function assertTable(string $table): void {
        if (!in_array($table, self::TABLES, true)) {
            error_log('Database: rejected unknown table identifier');
            throw new RuntimeException('Unknown table identifier.');
        }
    }

    private static function dsn(): string {
        $host = (string)Env::get('DB_HOST', '127.0.0.1');
        $port = (string)Env::get('DB_PORT', '5432');
        // If a pasted host already carries a port, honor the trailing value instead of doubling it up.
        if (preg_match('/^(.*):(\d+)$/', $host, $m)) { $host = $m[1]; $port = $m[2]; }
        return 'pgsql:host=' . $host . ';port=' . $port . ';dbname=' . (string)Env::get('DB_NAME', 'postgres')
             . ';sslmode=' . (string)Env::get('DB_SSLMODE', 'require');
    }

    public static function pdo(): PDO {
        if (self::$pdo === null) {
            self::$pdo = new PDO(self::dsn(), (string)Env::get('DB_USER', ''), (string)Env::get('DB_PASS', ''), [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                // Client-side prepares: the transaction-mode pooler (port 6543) does not support
                // server-side prepared statements, and session-level SETs cannot be relied on.
                PDO::ATTR_EMULATE_PREPARES => true,
            ]);
        }
        return self::$pdo;
    }
    public static function query(string $sql, array $params = []): PDOStatement {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        return $st;
    }

    /** INSERT that returns the new table id (used everywhere lastInsertId() was used with MySQL). */
    public static function insert(string $sql, array $params = []): int {
        return (int)self::query(rtrim($sql) . ' RETURNING id', $params)->fetchColumn();
    }

    /** Run $fn inside a transaction; roll back and rethrow on any failure. */
    public static function transaction(callable $fn): mixed {
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try { $r = $fn(); $pdo->commit(); return $r; }
        catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
    }
    /** Which of these ids really exist? $table is always a code constant. */
    public static function existing(string $table, array $ids): array {
        self::assertTable($table);
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($i) => $i > 0)));
        if (!$ids) return [];
        $in = implode(',', array_fill(0, count($ids), '?'));
        return array_map('intval', self::query("SELECT id FROM $table WHERE id IN ($in)", $ids)->fetchAll(PDO::FETCH_COLUMN));
    }
}
