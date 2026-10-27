<?php
/** Postgres-backed session storage: all container instances share one session table. */
final class DbSessionHandler implements SessionHandlerInterface {
    private const TTL = 28800; // 8 hours
    private bool $failed = false;

    public function open(string $path, string $name): bool { return true; }
    public function close(): bool { return true; }

    public function read(string $id): string|false {
        try {
            $v = Database::query('SELECT data FROM sessions WHERE id = ? AND expires_at > now()', [$id])->fetchColumn();
            return $v === false ? '' : (string)base64_decode((string)$v, true);
        } catch (Throwable $e) {
            $this->log('read', $e); return '';
        }
    }

    public function write(string $id, string $data): bool {
        try {
            Database::query(
                'INSERT INTO sessions (id, data, expires_at) VALUES (?, ?, now() + make_interval(secs => ' . self::TTL . '))
                 ON CONFLICT (id) DO UPDATE SET data = EXCLUDED.data, expires_at = EXCLUDED.expires_at',
                [$id, base64_encode($data)]
            );
            $this->failed = false;
            return true;
        } catch (Throwable $e) {
            $this->log('write', $e); return false;
        }
    }

    public function destroy(string $id): bool {
        try { Database::query('DELETE FROM sessions WHERE id = ?', [$id]); return true; }
        catch (Throwable $e) { $this->log('destroy', $e); return false; }
    }

    public function gc(int $max_lifetime): int|false {
        try { return Database::query('DELETE FROM sessions WHERE expires_at < now()')->rowCount(); }
        catch (Throwable $e) { $this->log('gc', $e); return false; }
    }

    /** True when an unexpected DB failure happened in this request ( surfaced via Session::lastDbFailure). */
    public function dbFailed(): bool { return $this->failed; }

    private function log(string $op, Throwable $e): void {
        $this->failed = true;
        // Never log SQL, identifiers, or any credential material.
        error_log('session ' . $op . ' failed: ' . rtrim((string)(preg_split('/\s+\(SQL:/', $e->getMessage())[0] ?? $e->getMessage())));
    }
}
