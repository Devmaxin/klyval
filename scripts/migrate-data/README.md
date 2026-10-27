# Klyval data migration: MySQL → Postgres

One-off tools. **Nothing here connects to the live legacy database** — the only
input is a plain-text SQL dump that the owner exports and saves locally.

## Inputs needed from the owner (before a real import)

1. A dump of the legacy InfinityFree MySQL database (phpMyAdmin → Export →
   SQL, or `mysqldump --complete-insert --default-character-set=utf8mb4`).
   Save it next to these scripts, e.g. `legacy-dump.sql`.
2. The legacy timezone setting, from running this on the live box:
   ```sql
   SELECT @@global.time_zone, @@session.time_zone, NOW(), UTC_TIMESTAMP();
   ```
   Datetimes in the dump are interpreted in `--source-tz` (default UTC).

The full `public/uploads/` folder is migrated separately once Supabase
Storage credentials exist (see `docs/DATA_MIGRATION.md` / `docs/DEPLOY.md`
for the planned `upload-media.php` step).

## What the importer does

`import-dump.php <dump.sql>`:

1. Parses every `INSERT` in the dump (string-literal aware: backslash
   escapes, doubled `''`, NULL, `utf8mb4`/emoji).
2. Keeps only tables that exist in both `database/migrations/*.sql` and the
   Postgres schema (`pg_tables`), and applies known column renames
   (`alt` → `alt_text`).
3. Excludes sessionful/ephemeral tables by default: `sessions`,
   `login_attempts`. More tables via `--exclude=...`.
4. Truncates (with `--truncate-first`; refuses otherwise if targets hold data),
   in reverse-dependency order.
5. Inserts parents before children, converts all legacy `DATETIME`/`TIMESTAMP`
   values from the source timezone into UTC (`timestamptz`).
6. Restores identity sequences via `setval(pg_get_serial_sequence(...),
   max(id))`.

Every row batch is wrapped in a transaction; any failure rolls back that
table's batch, and the script aborts on schema mismatch instead of guessing.

## Post-import verification

```bash
php scripts/migrate-data/import-dump.php  legacy-dump.sql --truncate-first
php scripts/migrate-data/verify-import.php --expect counts.json
```

`verify-import.php` prints per-table row counts (with expected counts from
counts.json, e.g. `{"projects": 123, "_total": 555}`), checks FK orphans
(top-10 references), verifies every identity sequence is ≥ `max(id)`,
exercises the actual app reads (`SiteContent::projectRedirect`, articles),
and verifies escape/utf8 integrity of known fixture rows.

## Files

- `import-dump.php` — dump parser + importer (CLI only)
- `verify-import.php` — read-only post-import checks
- `dump-cols.php` — throws-away helper to dump legacy MySQL column names
- `fixtures/mock-mysql.sql` — synthetic phpMyAdmin-shaped fixture; import +
  verify were proven against it (30 rows / 23 tables, VERIFY PASS)
