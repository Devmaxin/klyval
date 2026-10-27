# KLYVAL — Migration Verification (Phase 0)

Date: 2026-10-26. Mode: read-only against `KLYVAL_MIGRATION_AUDIT.md` and the actual source tree.
Method: full read of core/services/models/routes/middleware, all 12 MySQL migrations, controllers, `.env`
(inspected by key name and value *shape only* — no secret values were printed or copied), `.gitignore`,
`.htaccess`, views referenced by the audit, and a grep sweep for every MySQL-specific construct the
audit lists.

## 0. Environment reality check (important)

| Tool | Available in this sandbox? |
|---|---|
| Git | **Yes** (2.56.0, Windows) — but this folder **is not a git repo yet** |
| PHP CLI | **No** |
| Docker | **No** |
| Python | **No** (Store alias stub only — existing `qa/*.py` cannot run) |
| psql / Supabase CLI | **No** |
| Web search/fetch | Worked inconsistently during Phase 0; the Vercel "PHP with Vercel" guide URL returned 404 |

Consequence: nothing in this migration can be *executed* locally as-is (no PHP runtime, no Postgres,
no Docker). Runtime verification will require either (a) installing PHP 8.3 + Postgres locally
(owner approval needed for system installs), or (b) running all runtime checks in GitHub Actions CI.
Static verification (below) is solid; **no runtime claim is made**.

## 1. Audit findings that are confirmed correct [V]

Every checked item below was verified against the real files:

- **Stack**: no Composer/npm/framework/build step; hand-rolled micro-framework; `never` return type,
  `strict_types`, match, constructor promotion. Server-rendered templates, vanilla JS, hand-written CSS. ✓
- **Front flow**: `public/index.php` → `bootstrap.php` → security headers + CSP → maintenance gate →
  `Router::dispatch` with `routes/web.php`. All POST routes get `Csrf` middleware automatically (Router.php:9). ✓
- **Routes**: route table matches §3.3 exactly; all handlers exist; no JSON/API endpoints. ✓
- **MySQL-ism counts match exactly** (grep-verified):
  - `lastInsertId()`: 7 (`Vocabulary`, `MediaService`, `TeamMember`, `Service`, `Project`,
    `ContactSubmission`, `Article`)
  - `FIELD(...)`: 3 (`Service::adminList`, `Service::options`, `HomepageAdminController`)
  - `ON DUPLICATE KEY UPDATE ... VALUES()`: 3 (`Settings::save`, `Settings::setMedia`,
    `SettingsController@save` social links)
  - `INSERT IGNORE`: 1 (`Slug::recordChange`)
  - `INTERVAL`: 4 (`LoginThrottle` count + 2-day cleanup, `ContactSubmission::countRecent`)
- **Swallowed SQL errors**: `Settings::load()/media()/socials()` and `SiteContent::safe()` catch
  `Throwable` and return empty defaults. This is the top silent-failure risk exactly as stated. ✓
- **`.env`**: populated with `APP_ENV`, `APP_DEBUG`, `APP_URL`, `DB_HOST/NAME/USER/PASS` (all
  non-empty, InfinityFree-host-shaped), `CONTACT_EMAIL`, non-empty `TRUSTED_PROXY`; **`APP_KEY` is
  EMPTY** and `SETUP_KEY` is empty. S1/S3 confirmed. (`SOCIAL_*` present but unused by code.) ✓
- **`APP_KEY` fallback** to the hard-coded constant in `Security::key()` confirmed (Security.php:14). ✓
- **`TRUSTED_PROXY` semantics**: last XFF entry only; arbitrary-trust risk confirmed. ✓
- **Session cookie** `klyval_sid` uses `$_SERVER['HTTPS']` only → no `Secure` behind a TLS-terminating
  proxy (S4 confirmed; Vercel container will see plain HTTP). ✓
- **Auth weakness S5**: role/`is_active` copied into session at login and **never re-checked**
  (Auth.php, AuthController.php). A deactivated/demoted admin keeps a privileged session. ✓
- **CSRF**: enforced on every POST by the router, `hash_equals`, logout is POST. ✓
- **Login throttle**: DB-backed 10/IP and 8/account per 15 min, 429, probabilistic cleanup 1-in-50. ✓
- **Media pipeline**: finfo sniff, `getimagesize`, dimension/pixel caps, decode+re-encode via GD,
  random 32-hex filenames, no SVG; disk-coupled (`mkdir/unlink/scandir/is_uploaded_file`). ✓
- **Four `/uploads/` URL builders** confirmed: `SiteContent::img()` (site), `MediaService::url()`,
  `forms.php::fmedia()` (hard-coded `<img src="/uploads/...">`), plus `robots.txt` `Allow: /uploads/`. ✓
- **CSP** `img-src 'self' data:` only (Csp.php) — Storage images would be blocked. ✓
- **`url()`** prepends `APP_URL` unconditionally → absolute Storage URLs would be mangled (S8.3-2). ✓
- **Dashboard filesystem checks** in `AdminController` (`strayFiles`, `uploadsWritable`) + warning in
  `views/admin/dashboard.php:4` — replace with Storage health check. ✓
- **Missing fonts**: no `public/assets/fonts/` directory exists; layout preloads woff2 files. ✓
- **Dead code**: `work.js` exists in assets/js (need to confirm it's unloaded — grep below shows no
  template references). `qa/` contains obsolete Playwright harness (`server.py`, `t_*.py`). ✓
- **Migrations**: 12 files additive-only; 009 and 011 contain `ALTER TABLE ... ADD COLUMN/KEY`
  **without** `IF NOT EXISTS`; the runner splits on `;`. ✓
- **Schema**: all 25 tables + `schema_migrations` and every FK/index listed in §4.2–4.4 matches the SQL
  (verified line-by-line against `database/migrations/001–012`). FK delete rules match §4.3, including
  the RESTRICT rules on `project_technologies` and `team_skills`. ✓
- **Error log to file** (`storage/logs/php.log`, dir absent in package) confirmed (bootstrap.php:18). ✓
- **`Env::get()` prefers real `getenv()`** over the `.env` file — works with Vercel env vars. ✓
- **Sessionless public reads**: public pages start a session on every request today (session_start in
  bootstrap); contact/session throttling keys live in it. ✓

## 2. Audit findings that are inaccurate, incomplete, or need correction

### 2.1 Confirmed wrong: U4 ("5 MB" hard-coded in the admin media view)
The admin media view does **not** hard-code "5 MB" — `views/admin/media/index.php:5` prints
`<?= (int)$maxMb ?> MB` (computed from the `MediaService::MAX_BYTES` constant). Only **three**
hard-coded "5 MB" strings exist: `Csrf.php:9` (flash message), `MediaService.php:19` and `:28`. The
audit's advice stands, but the change is smaller than described: adjusting the constant + literal strings.
Notably, `views/admin/media/index.php` line 4-ish in the audit's list ("`src` must change to
`media_url()`") is still correct: the media select tiles hard-code `/uploads/`.

### 2.2 Correction: `media.width/height` types
MySQL uses `SMALLINT UNSIGNED` (max 65,535). The audit's Postgres schema widens these to `integer`.
That is a **safe widening** (fine), but the audit text doesn't acknowledge the difference. Keeping
`integer` is correct/approved — note in the data-parity report that dimensions may be up to 6000 (code
cap) and re-encoded results ≤2400.

### 2.3 Correction: `contact_submissions.notes` column position
Migration 009 added `notes` **after** `message` (`AFTER message`). The audit's proposed Postgres schema
places `notes` between `message` and `status` — semantically identical (column order doesn't matter),
but keep the name-only mapping in the import tooling (import by column name, never by position).

### 2.4 Incomplete: `published_at` write path and Postgres session timezone (high-impact)
`Project::setStatus/save` and `Article::save` write `date('Y-m-d H:i:s')` (PHP `APP_TZ` = UTC) into a
datetime column. On MySQL the value was interpreted by the MySQL **session** timezone; on Postgres a
naive `timestamptz` insert is interpreted in the **Postgres session** TimeZone (Supabase default: UTC).
With `APP_TZ=UTC` and a UTC-default Supabase session these agree — but with the transaction-mode
pooler **no session-level `SET` is reliable**, so the connection string must not depend on a per-request
`SET`. Mitigation chosen: keep `timestamptz` + ensure values are imported explicitly with zone
(`timestamp AT TIME ZONE 'UTC'` transform in the loader) and never issue `SET TIME ZONE` at runtime.
The audit flags the timezone generally (§4.8-2) but does not connect it to the write path; documented
here. Do **not** use `timestamp without time zone`.

### 2.5 Incomplete: Caddyfile sketch ordering (audit §11.6)
The sketch mixes `respond @dotfiles 404`, `error @phpFile 404`, `file_server`, and `php_server` in
directive order that Caddy's router relies on. Caddy resolves **route blocks** with a fixed ordering;
as written, the `php_server` fallback (`try_files {path} index.php`) would run for paths like
`/app/core/Router.php` too? — actually `try_files` only rewrites when the file doesn't exist, and
`/app/...` files aren't under docroot, so it falls to `index.php` — the front controller would
**200-OK the home page for any private path** unless the 404 matcher wins. This needs a corrected
Caddyfile using ordered `route` blocks (verified by integration tests during Phase 5). Treat the sketch
as a starting direction only, not production-ready:

```
route {
  @blocked path /app/* /bin/* /config/* /database/* /deploy/* /docs/* /qa/* /routes/* /storage/* /views/* /supabase/* /scripts/*
  error @blocked 404
  @dotfiles path_regexp ^/\.(?!well-known(/|$))
  error @dotfiles 404
  @php path *.php*
  error @php 404
  @assets path /assets/*
  header @assets Cache-Control "public, max-age=31536000, immutable"
  file_server
  route {  # remaining requests → front controller
    php_server { try_files {path} index.php }
  }
}
```
(The final shape is decided by Phase 5 local container tests, not this sketch.)

### 2.6 Unverified platform claims ([U] until re-checked at implementation time)
- `vercel.json` "services + runtime: container" shape, FrankenPHP base image tag
  (`dunglas/frankenphp:1-php8.3-bookworm`), `$PORT` binding, and the 4.5 MB request-body cap were
  fetched by the audit from Vercel docs on 2026-10-26 but could **not** be re-verified here (web tooling
  flaky + the guide URL 404s). These remain plausible but **must be re-verified against current Vercel
  documentation and a live preview** before any production step (Phase 5 blocking check).
- Interval/pooler claims `ATTR_EMULATE_PREPARES=true` requirement: consistent with public Supabase
  Supavisor docs; also flagged for live verification.

### 2.7 Minor: audit file-change list omissions
- `app/controllers/MediaController.php` passes `$maxMb` to the view — it must be updated to reflect the
  new 4 MB limit chosen from `MediaService::MAX_BYTES` (mostly automatic), but also its flash/413 text
  in `Csrf.php` needs the "5 MB" wording updated (listed correctly).
- `qa/upload-test.php` hard-codes a 5 MB rejection test (line 33) and a `strayFiles()` assertion — these
  -will- break; they should be adapted to the Storage backend (test upload via the new service) rather
  than deleted silently.
- `bin/check-uploads.php` does an HTTP fetch of its own host (audit: "remove" — agreed).
- `app/services/Form.php`, `Enquiry.php`, `HomeContent.php`, `Sitemap.php`, `Taxonomy.php`, `View.php`,
  `ContactController`, `WorkController`, `InsightsController`, `SeoController`, `PageController`,
  `Vocabulary`, `Slug` were read and contain no MySQL-isms beyond what's listed in §4.7 — good.

### 2.8 Correction: `Database::existing()` and dynamic identifiers
`Database::existing($table, $ids)` takes `$table` from code constants only (grep audit confirmed).
Keep the rule and add an explicit allowlist assertion inside `Database::existing()` as defense-in-depth
(low-cost hardening; not a rewrite).

## 3. Remaining unknowns needing the owner's answers (blocks)

| # | Unknown | Blocks |
|---|---|---|
| U1 | **Full MySQL dump** of live data + which rows are placeholders; no dump exists in the repo | Data migration (Phase 6 import); tooling can be built against fixtures first |
| U2 | Live MySQL **time zone** (`@@global.time_zone`, `NOW()` vs `UTC_TIMESTAMP`) and version | Timestamp parity |
| U3 | Complete live `public/uploads` (repo has only 10 files; live DB row count on `media` unknown) | Media accounting |
| U4 | Whether a **GitHub repo** exists at all and under which account/org (none locally) | Phase 1 repository work |
| U5 | Live domain/DNS host, current traffic peak, whether the site is currently live on InfinityFree | Cutover plan |
| U6 | Vercel plan type and acceptance of Container Runtime **Beta**; region preference | Phase 5, cutover |
| U7 | Live admin user count (hashes migrate as-is) | Auth handover |
| U8 | Analytics provider/ID enabled? Maintenance mode currently ON on the live site? | CSP, cutover |
| U9 | Do you have the Oxford/Open Font License woff2 files for Sora and Inter? | Cosmetic fix U1 (audit) |
| U10 | Are all repo images referenced by `media` rows? | Data integrity |

## 4. Deployment and compatibility risks (ranked)

1. **Beta Container Runtime** [U] — must be validated by an actual preview deploy of the container
   before any other platform commitment.
2. **Silent empty pages on SQL dialect misses** — `Settings::*` and `SiteContent::safe()` swallow errors
   ⇒ porting tests must assert non-empty content AND fail on any swallowed-exception log line.
3. **Prepared statements vs the transaction pooler** — use client-side prepares
   (`ATTR_EMULATE_PREPARES => true`) and **no session-level `SET`** reliance.
4. **Request body cap 4.5 MB [U re-check]** — reduce app limit to 4 MB and update 3 strings.
5. **Timestamps/timezone** — preserved by explicit-zone import (§2.4) and UTC everywhere.
6. **Case-sensitivity** — `citext` for `admin_users.email`, `technologies.name`, `skills.name`.
   Pre-import duplicate tests on live data (can't run yet — no dump).
7. **RLS exposure of the Data API** — must be verified with an anon-key probe against a live **staging**
   project before any production step. Policies must target `klyval_app`; note that RLS does **not**
   apply to the table owner, so the app must **not** connect as the schema owner (Supabase's
   `postgres` role). This is already implied by the audit's dedicated-role design; make it a hard rule.
8. **`Secure` cookie behind Vercel TLS** — requires `X-Forwarded-Proto` handling verified in a preview.
9. **Cold starts / DB chattiness** — choose co-located region; optional caching later.

## 5. Dependency-ordered implementation plan (updated from audit §12.1)

1. **Phase 0 done** (this file).
2. **P1 baseline (Phase 1)**: ask owner about Git init; baseline commit excluding `.env` and
   `public/uploads/*`; add `.env.example`; tighten `.gitignore` (allow `.env.example`); keep MySQL
   migrations under `legacy/` or in the baseline tag for rollback; secret-scan tracked files (none
   tracked — repo has no history).
3. **P2 Postgres code port (Phase 2)**: write `supabase/migrations/0001…0012` from the audit's §10.4
   + §10.5 with corrections in §2 above; port `Database.php` (pgsql DSN, `DB_PORT`, `DB_SSLMODE`,
   emulation, `insert()` with RETURNING, allowlist hardening); port the 7 `lastInsertId`, 3 `FIELD`,
   3 upsert, 1 INSERT IGNORE, 4 INTERVAL call sites; stderr logging; `APP_KEY` fail-closed in prod;
   local execution via GitHub Actions CI (installing PHP/Postgres locally requires owner approval).
4. **P3 sessions/auth (Phase 3)**: `DbSessionHandler` + `session_set_save_handler`; `is_https()`;
   per-request `is_active`/role re-check; client-IP decision pending a preview header echo.
5. **P4 storage (Phase 4)**: `MediaService` → Supabase Storage REST; `media_file_url()/media_origin()`;
   update the four `/uploads/` builders, `Csp`, `url()`, `AdminController` + dashboard, and the 3
   "5 MB" strings → 4 MB; orphan-avoidance (compensating deletes).
6. **P5 container (Phase 5)**: corrected `Caddyfile` (§2.5), `Dockerfile.vercel`, `vercel.json`,
   `.dockerignore`; local docker build + curl smoke tests (requires Docker locally or CI).
7. **P6 data tooling (Phase 6)**: `scripts/migrate-data/` with the fallback plan (phpMyAdmin CSV
   export) built-in; synthetic fixtures verify the pipeline end-to-end while the real dump is missing.
8. **P7 tests + staging (Phase 7)**: PHP test runner covering all audit §12.4 cases; RLS anon probe;
   strict no-swallowed-error assertion.
9. **P8 docs (Phase 8)**: README rewrite, `docs/DEPLOY.md` with rollback plan.

## 6. Exact tests required to establish readiness (acceptance criteria)

Must exit zero/fail loudly in CI:

T1  `php -l` on every PHP file.
T2  MySQL-ism grep (audit T3):
    `grep -rnE "lastInsertId\|FIELD\(\|ON DUPLICATE\|INSERT IGNORE\|INTERVAL [0-9\"]" app bin` → 0 hits.
T3  `grep -rn "/uploads/" app views` → only the new `media_url()` definition point, no literal.
T4  `grep -rn "storage/logs" app` → 0 hits; `grep -rn "5 MB" app` → 0 hits.
T5  Migrations apply cleanly from empty Postgres (`supabase db reset` or psql each file in order, twice
    for idempotency checks where the migrator supports it).
T6  RLS probe: using the **anon** REST key, `GET /rest/v1/<every_table>?select=*&limit=1` → empty or
    401 for each of the 26 tables (+ sessions), and POST → 401/403.
T7  App-role probe: `klyval_app` (or the actual connect role) can CRUD every table; inserting an id
    explicitly works (identity BY DEFAULT) and `setval` on sequences is permitted.
T8  Smoke: every public route returns the audit §12.4 expected status with non-empty body and no SQLSTATE.
T9  Admin flows: login/logout, 429 throttle, CSRF 419, editor 403 on admin-only, stale-session
    invalidation after deactivation (per-request re-check).
T10 Upload: JPEG/PNG/WebP accepted; oversize (>4 MB) rejected; wrong mime rejected; tiny/pixel-bomb
    rejected; display URL renders; delete blocked when in use; orphan cleanup on DB failure (compensating delete verified by test that removes the storage object when the `media` INSERT fails).
T11 docker build → curl: `/` 200 (non-empty), `/index.php` 404, `/.env` 404, `/app/core/Router.php` 404,
    `/assets/...` immutable cache header, `php -m` includes pdo_pgsql gd mbstring fileinfo iconv curl exif.
T12 Data parity tooling dry-run on synthetic fixtures (counts, id ranges, FK integrity, checksums,
    media accounting, admin account list).

Anything here that cannot run locally (T5 with real Postgres, T11) must be run in CI before claiming success.

## 7. Things this sandbox could NOT verify
- No PHP execution → no runtime behavior observed at all.
- No Postgres/Docker → no schema or container validation.
- Vercel docs claims re-check blocked by web tooling flakiness; treat §9/§11 as [U] until a live preview.
- Live InfinityFree DB unreachable/read-like behavior unknown; no dump provided.
