# Klyval — deployment on Vercel (Container Runtime) + Supabase

Target stack, resulting from the InfinityFree migration:
**PHP 8.3 via FrankenPHP (Caddy-in-binary) inside a container on Vercel**,
**Supabase PostgreSQL** (schema in `supabase/migrations/`) and
**Supabase Storage** for uploaded media (bucket `media`, 4 MiB limit).

Anything marked **[U]** was recorded from Vercel/Supabase docs but must be
re-verified against current documentation and a real preview deploy before
production cutover.

---

## 1. Prerequisites (owner accounts/secrets)

- GitHub repository with this code pushed.
- **Supabase project** — get: project URL, Postgres connection string
  (use the **session pooler** port 5432, or the transaction pooler 6543 — the
  DB layer already runs with `ATTR_EMULATE_PREPARES=true`, required by 6543),
  service_role key for Storage.
- **Vercel account** — confirm the plan accepts Container Runtime **[U]** and note the region.
- One-time: answer the audit questions from the live box before importing data:
  ```sql
  SELECT @@global.time_zone, @@session.time_zone, NOW(), UTC_TIMESTAMP();
  ```
  plus a full export: phpMyAdmin → Export → SQL (saved as `legacy-dump.sql`),
  and the whole `public/uploads/` folder.

## 2. Environment variables (set in Vercel, never in a committed file)

| Var | Meaning |
|---|---|
| `DB_HOST` | Supabase pooler host (e.g. `aws-0-…pooler.supabase.com`) |
| `DB_PORT` | `6543` (transaction pooler) or `5432` |
| `DB_NAME` | `postgres` (or the configured Postgres DB name) |
| `DB_USER` | pooler user (e.g. `postgres.<ref>`) |
| `DB_PASS` | database password (rotate it! the old one appeared in a local `.env`) |
| `DB_SSLMODE` | `require` (default) |
| `APP_URL` | `https://your-domain` |
| `APP_KEY` | long random string (keys the throttles) |
| `APP_TZ` | `UTC` |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `0` |
| `TRUSTED_PROXY` | `1` (Vercel edge is the trusted proxy) |
| `SUPABASE_URL` | project URL |
| `SUPABASE_SERVICE_ROLE` | service_role key (Secret, never exposed) |
| `MEDIA_BASE` | `https://<ref>.supabase.co/storage/v1/object/public/` (leave unset to use the default builder) |

## 3. Database setup (Supabase)

1. From the Supabase CLI or SQL editor, apply the 12 migrations in order:
   `supabase/migrations/0001…0012_*.sql` (0011/0012 are platform-safe).
2. Import data **before** going live:
   ```bash
   php scripts/migrate-data/import-dump.php legacy-dump.sql --truncate-first --source-tz=UTC
   php scripts/migrate-data/verify-import.php --expect counts.json
   ```
3. Upload `public/uploads/*` to the `media` bucket (MediaService names must
   match the `filename` column values in `media`).
4. Rotate the DB password and the admin password afterwards; never reuse the
   credentials found in the local `.env`.

## 4. Deploy (Vercel)

1. Connect the GitHub repo to Vercel; the root `vercel.json` already declares the
   container service `web` (`Dockerfile.vercel`).
2. First deploy must be a **preview**; validate:
   - every public route (`qa/dev-server.php` parity table in `docs/MIGRATION_VERIFICATION.md` T8);
   - `/admin/login` POST + CSRF + throttle + logout;
   - media upload through Admin → Media lands in Supabase Storage and renders;
   - `Retry-After: 3600` 503 maintenance page as an anonymous visitor;
   - secured cookies (`Secure`, `HttpOnly`) — automatic on HTTPS.
3. Promote to production only after the preview passes; then move DNS (keep
   InfinityFree as rollback until DNS TTL expires).

## 5. Rollback

Push the tagged baseline `legacy-infinityfree` (`442eeb9`) back to InfinityFree
(FTP) — media/DB there are untouched. This continues working as long as DNS points at it.

## 6. Remaining acceptance items before cutover

- [ ] MySQL dump from owner (blocked until provided) — run importer + verifier for real.
- [ ] Timezone answer from live box.
- [ ] Supabase Storage contract verified live (`MediaService::put`/`remove`/`storageHealthy`).
- [ ] Preview-deploy container test: `$PORT` binding + request-body cap vs 4 MiB upload.
- [ ] `POST /contact` throttling verified on the preview (5/hour per address).
- [ ] Caddyfile route-block ordering proven in the container (parity with local test).
