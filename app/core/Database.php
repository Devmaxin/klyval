<?php
final class Database {
    private static ?PDO $pdo = null;
    public static function pdo(): PDO {
        if (self::$pdo === null) {
            $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', Env::get('DB_HOST', '127.0.0.1'), Env::get('DB_NAME', 'klyval'));
            self::$pdo = new PDO($dsn, Env::get('DB_USER', ''), Env::get('DB_PASS', ''), [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        }
        return self::$pdo;
    }
    public static function query(string $sql, array $params = []): PDOStatement {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        return $st;
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
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($i) => $i > 0)));
        if (!$ids) return [];
        $in = implode(',', array_fill(0, count($ids), '?'));
        return array_map('intval', self::query("SELECT id FROM $table WHERE id IN ($in)", $ids)->fetchAll(PDO::FETCH_COLUMN));
    }
}
