# Klyval — Technical Audit & Migration Report
**Target:** GitHub + Vercel + Supabase  |  **Audit date:** 2026-10-26  |  **Mode:** read-only (nothing in the project was changed)

---

## 0. Scope, method and confidence legend

**What I inspected.** The uploaded archive `klyval__1_.zip` (204 files, 3.4 MB). I listed it, extracted a scratch copy outside the upload, and read every PHP file in `app/`, `routes/`, `bin/`, `deploy/`, all SQL migrations, `docs/schema.md`, `README.md`, all `.htaccess` files, the layouts/components/admin views (grep-audited for unescaped output), and all client JS/CSS (grep-audited for network calls, storage, fonts, breakpoints). `.env` was read **by variable name and value shape only**; no secret value appears in this report.

**What I could not do.**
- **Run the app.** PHP is not installed in my sandbox, so nothing here is based on runtime behavior.
- **See any data.** The archive has **no database dump**; only 10 image files exist in `public/uploads/`.
- **See Git history.** There is **no `.git` folder** in the archive (`not a git repository`).
- **Verify the live host.** InfinityFree behavior is taken from the README and code only.

**Confidence tags used throughout**

| Tag | Meaning |
|---|---|
| **[V]** | Verified directly in the files I read |
| **[W]** | Verified against current vendor documentation (Vercel/Supabase pages fetched 2026-10-26) |
| **[I]** | Inference from code; likely true but not proven |
| **[U]** | Unknown / cannot be verified from the archive; needs a human answer or a test |

---

## Executive summary

**What Klyval is.** A small, dependency-free, custom-framework **PHP 8.1+ / MySQL** marketing site for a digital studio, with a built-in admin CMS. ~2,860 lines of PHP, 50 view templates, 14 JS and 8 CSS files, **no Composer, no npm, no build step** [V]. It is currently packaged for **InfinityFree shared Apache hosting** [V].

**Headline conclusions**

1. **Do not just upload this to Vercel.** Vercel has no first-party PHP runtime. As of the docs I fetched, PHP runs on Vercel either through the **Container Runtime (Beta, all plans)** with FrankenPHP, or the **community `vercel-php` runtime** [W]. Either way the filesystem is not persistent, which breaks three things in this app: **PHP file sessions, image uploads to `public/uploads/`, and log files** [V].
2. **No rewrite is needed.** The app has no email, payment, AI, webhook, cron, queue or WebSocket code [V]. The required work is a **database dialect port (MySQL → Postgres), a DB-backed session handler, moving uploads to Supabase Storage, and a container wrapper**. Roughly 20 PHP files change, mostly small edits.
3. **Recommended destination:** Vercel Container Runtime (FrankenPHP) + Supabase Postgres (via pooler) + Supabase Storage. **Keep the existing custom admin login** for cutover; Supabase Auth adds complexity without security benefit here (§5, §10).
4. **Highest risks:** (a) Container Runtime is **Beta** [W]; (b) `SiteContent`/`Settings` **swallow database errors and render empty pages**, so a missed MySQL-ism would fail silently as a blank site [V]; (c) Supabase's auto-exposed Data API means **RLS must be enabled on every table** or enquiries and password hashes would be publicly readable [W/I]; (d) the **CSP blocks any image host except `'self'`** [V], so Storage images will not display until the CSP is updated; (e) Vercel's **4.5 MB request cap** [W] is below the app's 5 MB upload limit [V].
5. **Security/hygiene findings to act on now:** the archive contains a **populated `.env` with live-looking database credentials** [V]; `APP_KEY` is **empty**, so the HMAC key falls back to a constant hard-coded in source [V]; fonts referenced by the layout are **missing** [V].
6. **Biggest unknown:** the actual data. I need a MySQL dump (and the live timezone setting) before the data migration can be called reliable (§4.8, §13).

---

## 1. Project overview

### 1.1 What Klyval does and for whom
- **Purpose [V]:** public website for "Klyval", a digital studio ("We build. We create. We launch.") plus a private **admin CMS** to manage its content. Copy in `PageController`/`Taxonomy` defines three service pillars: **Build, Create, Launch**.
- **Target users [I, from copy and features]:**
  - *Public visitors / prospective clients* browsing work, services, team, insights, and sending a project enquiry.
  - *Studio staff* signing into `/admin` as **admin** (everything) or **editor** (content only).

### 1.2 Feature inventory and status

Status reflects **code completeness** [V]. Nothing was executed, so "Implemented" means "code present, routed, and internally consistent", not "tested live".

| Area | Feature | Status | Notes |
|---|---|---|---|
| Public | Home (`/`): intro animation, project "wall", services, studio, team, process, stats, CTA, optional banner | Implemented | Sections auto-hide when empty; admin toggles per section |
| Public | Work list `/work` + filter by kind; project page `/work/{slug}` | Implemented | Filter uses real links (`/work?kind=…`); canonical stays `/work` |
| Public | Studio `/studio`, Services `/services`, Team profile `/team/{slug}` | Implemented | |
| Public | Insights `/insights`, `/insights/{slug}` | Implemented | Returns 404 until ≥1 published article exists (by design) |
| Public | Contact form `/contact` (POST stores to DB) | Implemented | Honeypot, min-time, per-session and per-IP throttle; **no email notification** |
| Public | `/sitemap.xml`, `/robots.txt`, JSON-LD, canonical/OG meta | Implemented | Placeholder content excluded and `noindex` |
| Public | 301 redirects from old slugs (projects, team, articles) | Implemented | `*_slug_history` tables |
| Public | Maintenance mode (503 page) | Implemented | Setting `maintenance_mode`; signed-in admins bypass |
| Public | Optional analytics (Plausible or GA4) | Implemented | Chosen in admin Settings; CSP allow-lists provider hosts |
| Admin | Login / logout, roles, DB-backed login throttle | Implemented | See §5 |
| Admin | Dashboard (counts, recent enquiries, health warnings) | Implemented | Warnings are filesystem-based (§3.5) |
| Admin | Projects CRUD (+ gallery, team roles, technologies, linked services, status, SEO) | Implemented | Delete only for archived projects, typed-slug confirm, admin-only |
| Admin | Team CRUD (+ skills, socials, project assignment) | Implemented | Delete only when inactive |
| Admin | Services CRUD (+ features list) | Implemented | |
| Admin | Insights (articles) CRUD | Implemented | Plain text only |
| Admin | Homepage: copy, banner, section toggles, featured picks, stats | Implemented | |
| Admin | Enquiries: list, filter, status, internal notes | Implemented | |
| Admin | Media library: upload, alt text, delete (in-use protection) | Implemented | Image re-encoding via GD |
| Admin | Settings: contact info, SEO defaults, analytics, maintenance, social links, logo/OG/favicon | Implemented | Admin-only |

**Partial / missing / broken**

| Item | State | Evidence |
|---|---|---|
| Self-hosted fonts (`sora-var.woff2`, `inter-var.woff2`) | **Broken (missing)**: layout preloads them and `tokens.css` declares `@font-face`; `public/assets/fonts/` does not exist; system fallbacks apply | [V] `views/layouts/main.php`, `tokens.css`, `ls public/assets` |
| `.env.example` | **Missing**: README tells you to copy it | [V] |
| Contact email notifications | Missing (README: "deferred on purpose") | [V] no mail code anywhere |
| Admin user management, password change/reset, forgot-password | **Missing**: only CLI `bin/create-admin.php` or the one-time `setup.php` creates users | [V] |
| Article tags | Deferred | [V] README |
| `public/assets/js/work.js` | **Dead code**: not loaded by any page; README calls the old pinned "work" section obsolete | [V] |
| `qa/server.py`, `qa/t_work.py`, other Playwright `t_*.py` | Obsolete test harness for the old homepage markup | [V] README |
| `README.md` | Partly stale (still says "Phases 1–2", "Insights pages are deferred") | [V] |
| Seeded content | None by design; **real content state is unknown** (no dump) | [V]/[U] |

---

## 2. Technology stack

| Layer | Finding | Tag |
|---|---|---|
| Backend language | **PHP ≥ 8.1** (`never` return type in `helpers.php`, `match`, `str_contains`, constructor promotion, `declare(strict_types=1)`) | [V] |
| Framework | **None**: hand-rolled micro-framework: `Router`, `View`, `Auth`/`AdminOnly` middleware, `Csrf`, `Csp`, `Session`, `Database` (PDO), `Env` | [V] |
| Frontend | Server-rendered PHP templates; **vanilla JS** (14 files) and hand-written CSS (8 files, ~64 KB JS+CSS total). No bundler, no framework | [V] |
| Package managers / deps | **None**: no `composer.json`, `package.json`, or `vercel.json` | [V] |
| Build tooling | **None** (no compile step) | [V] |
| Database | **MySQL 5.7+ / MariaDB 10.3+** via `PDO mysql`, charset `utf8mb4`, InnoDB | [V] README + `Database.php` |
| PHP extensions required | `pdo_mysql`, `mbstring`, `gd` (JPEG/PNG/WebP), `fileinfo`; `exif` optional; `iconv` used by `Slug` (guarded by `function_exists`) | [V] |
| Web server | Apache `mod_rewrite` + `.htaccess` rules (primary); Nginx example for VPS | [V] |
| Current hosting | **InfinityFree** shared hosting, web root = `htdocs`, FTP deploy, no SSH, one-time `setup.php` installer. `.env` `DB_HOST` has an InfinityFree-style hostname | [V] README/deploy/`.env` shape |
| Deploy architecture | Upload files by FTP → open `/setup.php` → run migrations + create first admin → delete `setup.php` | [V] |
| Local test tooling | `qa/smoke.sh`, `qa/security.sh` (need `php -S` + a scratch MySQL), `qa/upload-test.php`, `qa/audit.py`, `qa/htaccess_check.py`, `qa/lint_migrations.py` | [V] |

**Runtime requirements to reproduce in the new environment:** PHP 8.1+ (recommend **8.3**: the repo's own Nginx example targets `php8.3-fpm` [V], and it avoids unverified 8.4 deprecation surprises [I]); extensions `pdo_pgsql` (new), `gd`, `mbstring`, `fileinfo`, `iconv`, `exif` (optional), `curl` (new, for Storage API calls).

---

## 3. Application structure

### 3.1 Directory map

| Path | Purpose | Migration relevance |
|---|---|---|
| `index.php` (root) | One-line shim: `require public/index.php` for hosts where web root = project folder | **Remove** (InfinityFree-only) |
| `public/index.php` | **Real front controller**: bootstrap, security headers, CSP, maintenance gate, router dispatch | Keep |
| `public/assets/{css,js}` | Static assets, versioned by `?v=filemtime` | Keep; serve from image/Caddy |
| `public/uploads/` | Image uploads written by PHP at runtime (10 files, 3.2 MB) | **Move to Supabase Storage**; remove from repo layout |
| `app/core/` | `Env, Database, Session, Router, Csrf, Csp, Security, LoginThrottle, View` | **Several change** |
| `app/helpers/` | `bootstrap.php`, `helpers.php`, `forms.php` | Small changes |
| `app/middleware/` | `Auth`, `AdminOnly` | Keep (optional hardening) |
| `app/controllers/` | 17 controllers (public + admin) | 3 change |
| `app/models/` | `Project, Article, Service, TeamMember, Enquiry, ContactSubmission` | 5 change |
| `app/services/` | `SiteContent, Settings, MediaService, Migrator, Slug, Vocabulary, Taxonomy, HomeContent, Sitemap, Form` | 5 change; `Migrator` removed |
| `routes/web.php` | Route table | Keep |
| `views/` | 50 PHP templates (layouts, components, pages, admin) | 2 admin views change |
| `database/migrations/` | 12 MySQL migrations (001–012) | **Replace** with Postgres migrations |
| `bin/` | CLI: `migrate`, `create-admin`, `purge-placeholders`, `check-uploads` | Replace/adapt |
| `deploy/` | `infinityfree-setup.php`, `nginx.conf.example` | **Remove** |
| `qa/` | Test harness | Keep selectively; exclude from image |
| `docs/schema.md` | Generated schema map (from MySQL migrations) | Regenerate or retire |
| `.htaccess` ×11 | Apache routing/deny rules in every folder | **Remove** (replaced by Caddy config) |
| `storage/` | Contains only `.htaccess`; app expects `storage/logs/php.log` (dir does not exist in archive) | Replace with stderr logging |
| `.env` | **Populated** (see §8) | **Never commit**; replace with Vercel env vars |
| `.gitignore` | Ignores `.env`, logs, and **all of `public/uploads/*`** | Update |

PHP lines by area [V]: controllers 615, models 195, services 523, core 179, helpers 133, middleware 32, views 1,020, routes 68, bin 56, deploy 37.

### 3.2 Entry points and request flow [V]
`public/index.php` → `app/helpers/bootstrap.php` (defines `ROOT`, autoloader, loads `.env`, error handling, starts the session) → security headers + **CSP** → **maintenance gate** → `Router::dispatch` using `routes/web.php`. POST routes automatically get the `Csrf` middleware; admin routes get `Auth` (+ `AdminOnly` for sensitive ones).

### 3.3 Routes

**Public**

| Method | Path | Handler |
|---|---|---|
| GET | `/` | `PageController@home` |
| GET | `/studio`, `/services` | `PageController@studio`, `@services` |
| GET | `/work`, `/work/{slug}` | `WorkController@index`, `@show` |
| GET | `/team/{slug}` | `TeamController@show` |
| GET | `/insights`, `/insights/{slug}` | `InsightsController@index`, `@show` |
| GET | `/sitemap.xml`, `/robots.txt` | `SeoController@sitemap`, `@robots` |
| GET / POST | `/contact` | `ContactController@show`, `@submit` |

**Admin** (all require sign-in; **admin-only** marked ★)

| Area | Routes |
|---|---|
| Auth | `GET/POST /admin/login`, `POST /admin/logout`, `GET /admin` (dashboard) |
| Media | `GET /admin/media`, `POST /admin/media/upload`, `POST /admin/media/{id}/alt`, ★`POST /admin/media/{id}/delete` |
| Settings | ★`GET/POST /admin/settings` |
| Projects | `GET /admin/projects`, `/new`, `POST /create`, `GET /{id}/edit`, `POST /{id}/save`, `POST /{id}/status`, ★`POST /{id}/delete` |
| Team | same shape under `/admin/team` (`/{id}/active` instead of `/status`), ★delete |
| Services | same shape under `/admin/services`, ★delete |
| Insights | same shape under `/admin/insights`, ★delete |
| Homepage | `GET/POST /admin/homepage` |
| Enquiries | `GET /admin/enquiries`, `GET /admin/enquiries/{id}`, `POST /admin/enquiries/{id}/update` |

All routed controller actions exist (checked programmatically) [V]. There are **no JSON/API endpoints**; the app is entirely server-rendered form posts.

### 3.4 Server-side logic and background work
- **Background tasks, cron, queues, workers, WebSockets, long-running processes: none found** [V] (grepped `cron|queue|websocket|exec|proc_open|curl_|mail(`; only `Migrator` uses `PDO::exec`).
- The only "maintenance" job is **probabilistic in-request cleanup**: `LoginThrottle::fail()` deletes old `login_attempts` rows 1 time in 50 [V]. This works on serverless as-is.
- Image processing (GD resize/re-encode) happens **synchronously in the upload request** [V].

### 3.5 Code coupled to the current hosting environment

| Location | Coupling | Required change |
|---|---|---|
| `Database.php` | `mysql:` DSN, `ATTR_EMULATE_PREPARES => false` (server-side prepares) | pgsql DSN; client-side prepares (pooler transaction mode forbids prepared statements [W]) |
| `Session.php` | PHP native **file** sessions; `Secure` flag from `$_SERVER['HTTPS']` | DB-backed handler; honor `X-Forwarded-Proto` (container sees plain HTTP behind Vercel TLS [I]) |
| `MediaService.php` | Writes/reads/deletes files in `ROOT/public/uploads`; `is_uploaded_file`, `mkdir`, `unlink`, `scandir` | Supabase Storage via REST |
| `SiteContent::img`, `MediaService::url`, `forms.php::fmedia`, `views/admin/media/index.php` | Four places hard-code `'/uploads/' . filename` | One `media_url()` helper → Storage public URL |
| `helpers.php::url()` | Prefixes `APP_URL` to any path; would corrupt an absolute Storage URL | Pass-through for `http(s)://` |
| `Csp.php` | `img-src 'self' data:` only | Add Storage host (derived from env) |
| `bootstrap.php` | Error log → `ROOT/storage/logs/php.log` (dir absent) | Log to stderr |
| `Security::ip()` | `REMOTE_ADDR` or last `X-Forwarded-For` if `TRUSTED_PROXY=1` | Verify what Vercel forwards [U] |
| `AdminController` + `views/admin/dashboard.php` | "uploads writable" / "stray files" checks use the filesystem | Replace with a Storage reachability check |
| `Migrator.php`, `bin/migrate.php` | Runtime MySQL migration runner (splits on `;`) | Replace with Supabase CLI migrations in CI |
| `deploy/infinityfree-setup.php`, root `index.php`, all `.htaccess`, `deploy/nginx.conf.example`, `bin/check-uploads.php` | Shared-host / Apache specifics | Remove |
| `Env.php` | Reads `.env` file, **but real env vars win** (`getenv()` first) | No change needed: works with Vercel env vars [V] |

---

## 4. Database audit

### 4.1 Current technology [V]
MySQL/MariaDB, InnoDB, `utf8mb4_unicode_ci` (a **case-insensitive** collation), accessed only through `Database::query()` (PDO prepared statements, `ERRMODE_EXCEPTION`, `FETCH_ASSOC`, **non-persistent** connections). A runtime `Migrator` applies `database/migrations/*.sql` in filename order, tracked in a `schema_migrations` table that the migrator creates itself (so there are **25 application tables + `schema_migrations`**).

### 4.2 Tables (from migrations 001–012) [V]

| # | Table | Purpose / key columns |
|---|---|---|
| 1 | `admin_users` | `id, name, email (UNIQUE), password_hash, role ENUM(admin,editor), is_active, last_login_at` |
| 2 | `site_settings` | key/value (`setting_key` PK): contact, SEO defaults, analytics, maintenance, footer |
| 3 | `homepage_settings` | key/value: hero/studio/CTA copy, banner, `show_*` section flags |
| 4 | `contact_submissions` | `name, email, company, project_type, budget, message, notes, status ENUM(new,reviewing,contacted,in_progress,closed), ip_hash` |
| 5 | `media` | `filename (UNIQUE), original_name, mime, width, height, bytes, alt_text, uploaded_by→admin_users` |
| 6 | `technologies` | `name (UNIQUE), slug (UNIQUE)` |
| 7 | `skills` | `name (UNIQUE), slug (UNIQUE)` |
| 8 | `projects` | `slug (UNIQUE), name, client_name, kind ENUM(client,product,experiment), description, case_study, cover_media_id, video_url, project_url, status ENUM(draft,published,archived), featured, display_order, seo_*, is_placeholder, published_at` |
| 9 | `project_slug_history` | `project_id, old_slug (UNIQUE)` for 301s |
| 10 | `project_media` | gallery: PK `(project_id, media_id)`, `display_order, caption` |
| 11 | `project_technologies` | PK `(project_id, technology_id)` |
| 12 | `team_members` | `slug (UNIQUE), name, role, portrait_media_id, short_bio, full_bio, portfolio_url, featured, display_order, is_active, is_placeholder` |
| 13 | `team_slug_history` | `member_id, old_slug (UNIQUE)` |
| 14 | `team_socials` | UNIQUE `(member_id, platform)`; platform ENUM incl. `website` |
| 15 | `team_skills` | PK `(member_id, skill_id)` |
| 16 | `project_team` | PK `(project_id, member_id)`, `role_label, display_order` |
| 17 | `services` | `slug (UNIQUE), name, pillar ENUM(build,create,launch), description, visual_media_id, show_on_home, display_order, is_active, is_placeholder` |
| 18 | `service_features` | `service_id, label, display_order` |
| 19 | `project_services` | PK `(project_id, service_id)` |
| 20 | `homepage_stats` | `label, value, display_order, is_active` (no seed rows) |
| 21 | `social_links` | `platform ENUM(x,instagram,linkedin,github,behance,dribbble,youtube,tiktok) UNIQUE, url, display_order, is_active` |
| 22 | `site_media` | `slot ENUM(logo,og_image,favicon)` PK → `media_id` |
| 23 | `login_attempts` | `ip_hash, email_hash, succeeded, created_at` (BIGINT id) |
| 24 | `articles` | `slug (UNIQUE), title, excerpt, content, cover_media_id, author_id→team_members, category ENUM(studio,build,create,launch), status ENUM(draft,published,archived), published_at, seo_*` |
| 25 | `article_slug_history` | `article_id, old_slug (UNIQUE)` |
| – | `schema_migrations` | `version PK, applied_at` (created at runtime by `Migrator`) |

Column-level detail is exactly as written in the 12 migration files and is reproduced in Postgres form in §10.4 and §14.4.

### 4.3 Relationships (all FKs, with delete rule) [V]

| Child → Parent | On delete |
|---|---|
| `media.uploaded_by → admin_users` | SET NULL |
| `projects.cover_media_id → media` | SET NULL |
| `project_media → projects` / `→ media` | CASCADE / CASCADE |
| `project_technologies → projects` / `→ technologies` | CASCADE / **RESTRICT** |
| `project_slug_history → projects` | CASCADE |
| `team_members.portrait_media_id → media` | SET NULL |
| `team_slug_history`, `team_socials → team_members` | CASCADE |
| `team_skills → team_members` / `→ skills` | CASCADE / **RESTRICT** |
| `project_team → projects` / `→ team_members` | CASCADE / CASCADE |
| `services.visual_media_id → media` | SET NULL |
| `service_features → services` | CASCADE |
| `project_services → projects` / `→ services` | CASCADE / CASCADE |
| `site_media.media_id → media` | SET NULL |
| `articles.cover_media_id → media` / `.author_id → team_members` | SET NULL / SET NULL |
| `article_slug_history → articles` | CASCADE |

`login_attempts` and `contact_submissions` have no FKs. **Data-quality note:** the app also enforces in code that `media` in use as a cover/portrait/visual/site slot cannot be deleted (`MediaService::usage`), and only archived projects/articles or inactive team/services can be hard-deleted [V].

### 4.4 Indexes and constraints [V]
Every table has a surrogate or composite PK. Unique keys: `admin_users.email`, `media.filename`, all `slug`/`old_slug` columns, `technologies/skills.name`, `social_links.platform`, `team_socials(member_id,platform)`. Listing indexes follow the `display_order, id` convention (e.g. `projects(status, display_order, id)`, `projects(featured, status, display_order, id)`, `projects(kind, status, display_order, id)`), plus `contact_submissions(status, created_at)`, `contact_submissions(ip_hash, created_at)`, three `login_attempts` indexes, and a covering index on every FK column. `TINYINT(1)` flags and `ENUM`s carry the constraints.

### 4.5 Migration files
12 files, additive only. **Caveats:** `009` and `011` contain `ALTER TABLE` without `IF NOT EXISTS` (safe only because the migrator tracks versions); the runner **splits on `;`** so comments/strings must not contain semicolons [V]. This runner cannot be reused for Postgres (functions, `DO` blocks, triggers).

### 4.6 Query and access patterns [V]
- **100% parameterized** values; dynamic **identifiers** (table/column/history names, `IN (?,?,…)` placeholders) come only from code constants.
- Public reads are centralized in `SiteContent`: one main query + **batched `IN (…)` hydration** (services/technologies/team/gallery/roles), avoiding N+1. By my count the homepage executes roughly **15–20 queries** per request across settings, media slots, footer socials, `hasArticles`, projects, pillars, team and stats [I, not measured].
- Writes use `Database::transaction()`; relation sets are **delete-then-reinsert** (`project_technologies`, `project_team`, `project_media`, `project_services`, `team_skills`, `team_socials`, `service_features`).
- **`SiteContent::safe()` and `Settings::load()/media()/socials()` catch `Throwable` and return empty defaults.** *On Postgres this converts any leftover MySQL syntax into a silently empty public site rather than an error.* Treat this as the top testing risk (§12).

### 4.7 What must change to run on Supabase PostgreSQL

| MySQL construct | Where (count) [V] | Postgres replacement |
|---|---|---|
| `lastInsertId()` | `ContactSubmission`, `Project`, `Article`, `Service`, `TeamMember`, `Vocabulary`, `MediaService` (7) | `INSERT … RETURNING id` via a new `Database::insert()` helper (PDO pgsql `lastInsertId` needs a sequence name) |
| `FIELD(pillar,'build','create','launch')` | `Service::adminList`, `Service::options`, `HomepageAdminController` (3) | `array_position(ARRAY['build','create','launch'], s.pillar::text)` or a `CASE` |
| `ON DUPLICATE KEY UPDATE … VALUES(col)` | `Settings::save`, `Settings::setMedia`, `SettingsController` social links (3) | `ON CONFLICT (key) DO UPDATE SET col = EXCLUDED.col` |
| `INSERT IGNORE` | `Slug::recordChange` (1) | `ON CONFLICT DO NOTHING` |
| `NOW() - INTERVAL n SECOND/MINUTE/DAY` | `LoginThrottle` (2), `ContactSubmission::countRecent` (1) | `now() - make_interval(secs => n)` / `mins =>` / `days =>` |
| `ENGINE=InnoDB … COLLATE …` | all DDL | remove |
| `INT UNSIGNED AUTO_INCREMENT` | all tables | `integer GENERATED BY DEFAULT AS IDENTITY` (**BY DEFAULT** so original IDs can be imported) |
| `TINYINT(1)` flags compared with `= 1` / set to `0/1` in ~30 places | many | **Recommended: `smallint` + `CHECK (x IN (0,1))`**. Using `boolean` would make `is_active = 1` a type error and force edits in ~30 queries; convert later if desired |
| `ENUM(...)` | 10 columns | `text` + `CHECK (col IN (...))` (taxonomy is owned by code, so no Postgres enum types) |
| `ON UPDATE CURRENT_TIMESTAMP` | `updated_at` on 12 tables | trigger `set_updated_at()` firing only when the row actually changes (mirrors MySQL semantics) |
| `TIMESTAMP` / `DATETIME` | all timestamps | `timestamptz` (see timezone caveat in §4.8) |
| `MEDIUMTEXT`, `CHAR(64)`, `VARCHAR(n)` | several | `text`, `char(64)`, `varchar(n)` |
| **Case-insensitive collation** | `admin_users.email`, `technologies.name`, `skills.name` and the queries `WHERE email = ?` / `WHERE name = ?` | Postgres is case-sensitive by default → use `citext` for those three columns, otherwise a user typing `Admin@…` stops matching |
| Server-side prepared statements | `ATTR_EMULATE_PREPARES => false` | Supabase **transaction-mode pooler does not support prepared statements** [W]; use `ATTR_EMULATE_PREPARES => true` (or pgsql `ATTR_DISABLE_PREPARES`) and verify in tests |
| `$pdo->exec()` migration runner | `Migrator` | Supabase CLI migrations (`supabase/migrations/*.sql`), applied from CI, not at runtime |

Constructs that already work as-is on Postgres [V]: `NOW()` (8 uses), `COALESCE`, `CASE`, `LIMIT (int)`, `COUNT(*)`, `GROUP BY`, `LEFT JOIN`, `SELECT DISTINCT … ORDER BY` (the ordered columns are already in the select list), `rowCount()` on DELETE, `fetchAll(FETCH_KEY_PAIR)`. `COUNT(*)` returns bigint (string in PDO); all call sites cast with `(int)` [V].

### 4.8 Data migration requirements (preserve existing data)

**Known:** the archive contains **no data**. 10 image files imply real content exists in the live DB [I]. **Unknown [U]:** row counts, which rows are placeholders, admin user count, MySQL version, and the **server timezone**.

Required approach:
1. **Export from the live MySQL** (InfinityFree usually offers phpMyAdmin and no SSH; whether remote MySQL access is allowed is **[U]**). Take a full dump (structure + data) and a separate copy of every file in `public/uploads/`.
2. **Capture timezone facts first:** run `SELECT @@global.time_zone, @@session.time_zone, NOW(), UTC_TIMESTAMP();` MySQL `TIMESTAMP` columns convert through the session zone while the app writes `published_at` with PHP `date()` in `APP_TZ` (UTC in `.env`) [V]. A wrong assumption shifts every timestamp and can make a published article look future-dated (`published_at <= NOW()` hides it).
3. **Hand-write the Postgres schema** (§14.4), don't auto-convert. Then load data **preserving original `id` values** (FKs and admin URLs depend on them), table by table in dependency order: `admin_users → media → technologies/skills → projects → team_members → services → articles → junction/history tables → settings/stats/social/site_media → contact_submissions → login_attempts (optional, can be dropped)`.
4. **Transform on load:** `TINYINT→smallint` as-is; ENUM strings as-is; `'0000-00-00 00:00:00'` → `NULL` (MySQL permits these, Postgres rejects them); timestamps → `timestamptz` using the zone found in step 2.
5. **Reset sequences** after import: `SELECT setval(pg_get_serial_sequence('public.<t>','id'), COALESCE(MAX(id),1)) FROM public.<t>;` for each identity table.
6. **Pre-import data checks (run on MySQL):** duplicate emails or technology/skill names differing only by case (would violate the new `citext` uniques); `media` rows whose file is missing and files with no row; `video_url/project_url/portfolio_url/social url` values not starting with `http(s)://` (the app validates on write, but imported rows bypass that); over-length strings.
7. **Verify:** per-table row counts, per-table `MIN/MAX(id)`, a checksum over key columns, and a visual pass of every public page and every admin list. Keep the MySQL dump untouched as the rollback source.
8. **`login_attempts` and `schema_migrations` should not be migrated** (ephemeral/obsolete). `admin_users.password_hash` **must** be migrated (see §5).
9. **Files:** upload the 10 images (and any others found on the live host) to a Storage bucket **under the same filenames** so `media.filename` stays valid with no row rewrite.

---

## 5. Authentication and authorization

### 5.1 Current implementation [V]

| Capability | Status |
|---|---|
| Registration | **None** (no public accounts) |
| Login | `POST /admin/login`: email + password; `password_verify` against `admin_users.password_hash` (PHP `PASSWORD_DEFAULT` → bcrypt `$2y$`); **constant-time dummy hash** when the email is unknown; generic error message; `is_active = 1` required |
| Brute-force protection | DB-backed `LoginThrottle`: 10 failures/15 min per IP hash, 8/15 min per account hash; returns 429 |
| Session | Native PHP **file** session cookie `klyval_sid`: `HttpOnly`, `SameSite=Lax`, `Secure` only when `$_SERVER['HTTPS']` is set, `use_strict_mode`, `session_regenerate_id(true)` on login, full destroy on logout. Stores `admin_id`, `admin_name`, `admin_role`, `_csrf`, `flash`, `contact_t`, `contact_last` |
| CSRF | Per-session 64-hex token, `hash_equals`, enforced on **every** POST route by the router; logout is a POST |
| Password reset / change | **None** (CLI/`setup.php` create admin only) |
| OAuth / SSO / MFA | **None** |
| Roles | `admin` and `editor`. `Auth` guards all admin routes; `AdminOnly` additionally guards Settings and all hard deletes (`media`, `projects`, `team`, `services`, `insights`) |
| Admin functionality | Full CMS (§1.2). No user-management UI |
| Weakness | Role and `is_active` are copied into the session at login and **never re-checked**; deactivating or demoting a user does not affect an existing session |

### 5.2 Recommended Supabase-compatible approach

**Phase 1 (cutover): keep the existing custom admin auth. Migrate `admin_users` as-is.**
- *Why:* every database access is server-side through PHP; no browser ever talks to Supabase. Supabase Auth's value (JWTs, per-user RLS) therefore buys no security here, while adopting it means replacing the login flow, throttle, and role model with GoTrue REST calls. The task says to avoid unnecessary complexity.
- Existing `$2y$` bcrypt hashes keep working with `password_verify`, so **no admin has to reset a password**.
- Required adjustments: sessions → Postgres (§10.6); `email` → `citext`; replace the CLI/`setup.php` bootstrap with a one-off script run against the new DB (§14.3).
- Cheap hardening to include (optional but recommended): re-read `is_active`/`role` from `admin_users` once per admin request (1 indexed query).

**Phase 3 (optional, later): move to Supabase Auth** only if you want password-reset emails, MFA, or magic links. Sketch: `auth.users` + `public.profiles(id uuid, role)`; PHP signs in via the GoTrue REST API (`/auth/v1/token?grant_type=password`) and verifies the JWT; bcrypt hashes can be imported into `auth.users.encrypted_password` (whether GoTrue accepts the `$2y$` prefix is **[U]**; if not, rewriting the prefix to `$2a$` is algorithmically equivalent [I]). Do not start this before the cutover is stable.

---

## 6. Third-party services and integrations

| Category | Finding [V] |
|---|---|
| Email (send) | **None.** Contact submissions are only stored; admins must look in `/admin/enquiries`. README lists notifications as "deferred" |
| Payments | None |
| AI APIs | None |
| File storage | Local disk only (`public/uploads`) |
| External HTTP calls from the server | None (`curl_*`, `file_get_contents(http…)` absent in app code; only `bin/check-uploads.php` fetches its own site) |
| Webhooks (inbound/outbound) | None → no signature-verification surface |
| Analytics | Optional **Plausible** (`plausible.io`) or **GA4** (`googletagmanager.com`, `google-analytics.com`), selected in Settings and allow-listed in the CSP. Client-side only |
| Other links | WhatsApp deep link (`wa.me`), social profile links from DB |
| Fonts | Self-hosted woff2 expected, **files missing** |

**Environment variables (names only, never values)**

| Variable | Used by code? | In shipped `.env`? | New-platform action |
|---|---|---|---|
| `APP_DEBUG` | Yes (`bootstrap.php`) | set | Vercel env, `0` in prod |
| `APP_URL` | Yes (`url()`) | set (https) | Vercel env: final public https URL, no trailing slash |
| `APP_TZ` | Yes | set (`UTC`) | Keep `UTC` |
| `APP_KEY` | Yes (HMAC for IP/email hashes) | **empty** | **Required:** 32+ random bytes; make app refuse to boot in production without it |
| `TRUSTED_PROXY` | Yes (`Security::ip`) | `0` | Decide after verifying Vercel headers [U] |
| `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` | Yes (`Database`) | set | Replace with Supabase **pooler** values; add `DB_PORT`, `DB_SSLMODE=require` |
| `CONTACT_EMAIL` | Fallback only, via `setting('contact_email')` when DB setting is empty | set | Optional |
| `APP_ENV` | **No** (never read) | set | Drop or keep informational |
| `SOCIAL_X`, `SOCIAL_INSTAGRAM`, `SOCIAL_LINKEDIN` | **No** (unused; socials come from `social_links`) | empty | Drop |
| `SETUP_KEY` | Only `deploy/infinityfree-setup.php` | empty | Drop with the installer |
| *(new)* `SUPABASE_URL`, `SUPABASE_SERVICE_ROLE_KEY` | Storage API from PHP | n/a | **Server-only, mark Sensitive in Vercel; never in views/JS** |
| *(new)* `MEDIA_BUCKET` (default `media`), `MEDIA_BASE_URL` | Public image URLs + CSP | n/a | Derived from `SUPABASE_URL` if unset |

`setting($key)` also falls back to an env var named `strtoupper($key)` for any key (`PHONE`, `WHATSAPP`, `FOOTER_TEXT`, `SEO_DEFAULT_TITLE`, …) [V], so those names are *implicitly* supported though none are set.

**Compatibility changes needed:** none for third parties. The only external dependency to introduce is Supabase (Postgres + Storage). If notification email is wanted later, add a provider (e.g. Resend) as a separate, optional change (§12).

---

## 7. Frontend and user experience

### 7.1 Pages and navigation [V]
- **Header:** Work · Studio · Services · Contact + "Start a project" button; mobile **menu toggle** (`nav.js`). **Footer:** page links, **Insights link only when an article is published**, email/phone/WhatsApp/social links from settings. Skip-to-content link present.
- **Public pages:** Home, Work (+filter), Project, Studio, Services, Team member, Insights (+article), Contact, 404, 413, 500, Maintenance.
- **Admin:** sidebar layout (Dashboard, Projects, Team, Services, Insights, Homepage, Enquiries, Media, + Settings for admins).

### 7.2 Responsiveness and accessibility [V]
Mobile-first CSS with breakpoints at 640/700/900 px (`@media` used in 7 of 8 stylesheets, 15× `min-width:900px`). `prefers-reduced-motion` is honored in 2 CSS files and 6 JS files. Semantic landmarks, `aria-current`, `aria-expanded`, error summaries with focus management on the contact form. *I did not render any page, so visual quality is unverified.*

### 7.3 Interactions that must be preserved

| Interaction | Mechanism |
|---|---|
| **Homepage intro** plays once per browser session, skipped for reduced motion, with a 9 s failsafe | `boot.js` (head) + `intro.js`, `sessionStorage['kv-intro']`, `components/intro.php` |
| **In-site page transitions** (skipped for modified clicks, new tabs, files, hash links, admin) | `transitions.js`, `sessionStorage['kv-pt']`, end of `motion.css` |
| **Opposite-direction project columns** ("wall"), live only with ≥4 published projects, disabled <700 px | `loop.js`, server flag `data-live` |
| **Work filter** with real links, only kinds that have projects | `work-filter.js` + `WorkController` |
| Custom cursor, magnetic buttons, process steps animation | `cursor.js`, `magnetic.js`, `process.js`, `motion.js` |
| **Contact flow:** CSRF, honeypot `website`, ≥3 s dwell, 30 s per-session gap, 5/hour per IP hash, post-redirect-get, character counter, double-submit guard | `ContactController`, `contact.js` |
| **SEO:** canonical, OG/Twitter meta, JSON-LD (Organization, WebSite, Article, CreativeWork), `/sitemap.xml`, 301 slug redirects, `noindex` for placeholders | layouts + `SeoController` |
| **Strict CSP** (no inline scripts) and `X-Frame-Options: DENY` | `Csp.php`, `public/index.php` |
| **Maintenance mode** 503 + `Retry-After` for non-admins | `public/index.php` |

### 7.4 Known UI/functional problems found in code

| # | Problem | Impact |
|---|---|---|
| U1 | Missing `fonts/sora-var.woff2` and `inter-var.woff2` → two 404 preloads + two 404 `@font-face` per page; typography falls back | Visual/perf; fix by adding the OFL font files [U: whether you have them] |
| U2 | `work.js` shipped but never loaded | Dead weight; remove |
| U3 | Dashboard health warnings ("uploads folder not writable", "unexpected files") are filesystem-based | Will be meaningless/false after migration; replace |
| U4 | Admin media screen and upload error text say "under 5 MB" | Must change to match the new limit (§9) |
| U5 | Error page for oversize POST is produced by PHP (`pages/413`) | On Vercel the platform rejects >4.5 MB before PHP runs, so editors will see a platform error instead |
| U6 | Stale README / obsolete QA scripts | Documentation debt |

---

## 8. Security audit

Severity is for the **current code plus the planned migration**. "No finding" items are listed so the next engineer does not re-audit them.

### 8.1 Findings

| ID | Sev | Finding | Evidence | Recommended action |
|---|---|---|---|---|
| S1 | **High** (process) | The archive contains a **populated `.env`** including DB host/name/user/password (values present, non-empty) and a contact email. Anyone who received this zip has the database credentials | `.env` shape check: `DB_*` non-empty, `DB_HOST` looks like an InfinityFree host [V] | **Never commit it** (`.gitignore` already lists it). Rotate the DB password now, and again when the old DB is retired. Share future archives without `.env` |
| S2 | **High** (migration-induced) | Supabase exposes `public` tables through its REST Data API using the public `anon` key unless RLS blocks it. Without RLS, `contact_submissions` (PII: names, emails, messages) and `admin_users.password_hash` would be readable, and tables writable | Supabase default behavior [W/I]; the schema has no RLS today because MySQL has none | **Enable RLS on all tables with no anon/authenticated policies, revoke grants from `anon`/`authenticated`, and connect PHP with a dedicated role** (§10.5). Add a test that the anon key gets zero rows from every table |
| S3 | Medium | `APP_KEY` is **empty** in the shipped `.env`, so `Security::key()` falls back to the literal string compiled into the source. Stored `ip_hash` / `email_hash` values (HMAC-SHA256) can be brute-forced offline (IPv4 space is small) by anyone who obtains the DB | `Security.php`, `.env` (`APP_KEY` length 0) [V] | Set a 32+ byte random `APP_KEY` in Vercel; make production **fail closed** if unset. Note: changing it invalidates existing throttle rows (harmless) and makes old `contact_submissions.ip_hash` values non-comparable (acceptable) |
| S4 | Medium | **`$_SERVER['HTTPS']` is the only HTTPS signal.** Behind Vercel's TLS termination the container likely sees plain HTTP, so the session cookie would be issued **without `Secure`** | `Session.php`, `helpers.php::url()` [V]; proxy behavior [I] | Treat `X-Forwarded-Proto: https` as HTTPS (only when running behind the platform proxy) |
| S5 | Medium | Role and `is_active` are cached in the session and never re-validated | `Auth.php`, `AuthController.php` [V] | Re-check per admin request (1 query), or invalidate the user's DB sessions when deactivating |
| S6 | Low–Med | **Client-IP trust:** with `TRUSTED_PROXY=1` the app takes the *last* `X-Forwarded-For` entry. If the platform does not append the client IP last, throttles key on the wrong address (all visitors could share one bucket and lock each other out, or an attacker could spoof) | `Security::ip()` [V]; Vercel container semantics **[U]** | Verify in a preview deployment by echoing headers; adjust to the header Vercel documents for the client IP |
| S7 | Low | No SQL injection found. All values bound; dynamic identifiers only from constants: `Slug::taken/recordChange` (history table/column names), `Vocabulary::ids`, `Settings::save`, `Database::existing`, `bin/purge-placeholders.php`; `LIMIT` values are `(int)`-cast | Read every query [V] | Keep the "identifiers from code only" rule; after the port, keep `ATTR_EMULATE_PREPARES=true` safe by never interpolating values |
| S8 | Low | **XSS:** output is escaped through `e()` (ENT_QUOTES); free text goes through `nl2br(e())`; JSON-LD uses `JSON_HEX_*`; analytics IDs are regex-validated; URLs are scheme-validated on write (`Form::url`); content is plain text only (no HTML editor); strict CSP without `'unsafe-inline'` scripts (only `style-src-attr 'unsafe-inline'` for inline `style=""` aspect ratios) | Grep of all `<?=` outputs [V] | No change. **Imported rows bypass write-time URL validation**, so re-validate URL columns during data migration (§4.8) |
| S9 | Low | **CSRF:** enforced on all POST routes via the router, token compared with `hash_equals`, `SameSite=Lax`; logout is POST | `Router.php`, `Csrf.php` [V] | No change. Token is not rotated at login (session id is); acceptable |
| S10 | Low | **Uploads are well handled:** size cap, `finfo` content-type sniff, `getimagesize`, pixel/dimension caps (decompression-bomb guard), **GD decode + re-encode** (strips metadata/payloads), random 32-hex server-side filenames, no SVG | `MediaService.php` [V] | Keep all of it when switching the storage backend; additionally set bucket `allowed_mime_types` and `file_size_limit` |
| S11 | Low | Throttle/anti-spam state depends on correct IP + working sessions | `LoginThrottle`, `ContactController` [V] | Covered by S6 and the DB-session handler |
| S12 | Info | Installer `deploy/infinityfree-setup.php` is a risk if left on a server (guarded by `SETUP_KEY` ≥24 chars and refuses if an admin exists) | [V] | **Remove from the new repo** |
| S13 | Info | **Sensitive operations are not exposed to the client.** No API keys in JS/CSS; all DB access and (future) Storage service key stay server-side; client JS makes **no network calls** (`fetch`/`XHR` absent) | grep of `public/assets/js` [V] | Keep `SUPABASE_SERVICE_ROLE_KEY` out of views, JS, logs and error pages |
| S14 | Info | Webhook verification | No webhooks exist [V] | n/a |
| S15 | Info | Error handling is safe: exceptions are logged, users see a generic 500; `APP_DEBUG=1` would print stack traces | `bootstrap.php` [V] | Ensure `APP_DEBUG=0` in Production **and Preview** |

### 8.2 Security headers present [V]
`X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy: strict-origin-when-cross-origin`, `Content-Security-Policy` (default-src 'self'; `frame-ancestors 'none'`; `base-uri 'self'`; `form-action 'self'`). **Missing:** `Strict-Transport-Security` (Vercel adds HSTS on its domains [I]; verify on the custom domain) and `Permissions-Policy` (optional).

### 8.3 Security-relevant breakages to expect from the migration
1. `Csp::build()` allows images only from `'self'` and `data:`, so **every Storage image will be blocked** until its host is added to `img-src` [V].
2. `url()` prepends `APP_URL` to whatever it is given; `og:image`, JSON-LD `logo`/`image` call `url(media_url(...))` [V], so absolute Storage URLs would become `https://site/https://…`. `url()` must pass absolute URLs through.
3. `robots.txt` contains `Allow: /uploads/` [V]; harmless but obsolete once images live on another host.

---

## 9. Vercel compatibility

### 9.1 What Vercel supports today (verified 2026-10-26) [W]
- **PHP is not an official runtime.** Vercel's docs list `vercel-php` only as a **community runtime** ("recommended by Vercel"); its README documents PHP up to **8.3**.
- **Container Runtime** (Dockerfile → OCI image → Vercel Function) is **in Beta on all plans** (docs last updated 2026-07-07). Vercel publishes official guides for **PHP with FrankenPHP** (Aug–Sep 2026): add `Dockerfile.vercel`, a `Caddyfile` bound to `$PORT`, and a `vercel.json` with `"runtime": "container"` and a catch-all rewrite. Functions scale to zero (idle 5 min in production, 30 s in preview), billed on **Active CPU** [W].
- Function limits: **4.5 MB request/response body**, 300 s default max duration; filesystem is **read-only except `/tmp`** and **not persistent** across instances [W].
- Vercel's PHP guides explicitly say **not** to use the local filesystem for uploads, sessions or queues, and to use an external pooler for databases [W].

### 9.2 Feature-by-feature compatibility

| Klyval feature | Compatible as-is? | Why / required change |
|---|---|---|
| PHP front controller + custom router | No (needs runtime) | Package with FrankenPHP container (recommended) or `vercel-php` |
| Apache `.htaccess` routing, deny rules, upload-exec protection, cache headers | **No** | Replace with `Caddyfile`: docroot `public/`, front-controller fallback, 404 for stray `.php`, deny dotfiles, `Cache-Control` for `/assets/*` |
| Native PHP file sessions | **No** (ephemeral, multi-instance) | DB-backed session handler (Postgres `sessions` table) |
| Image uploads written to `public/uploads` | **No** | Supabase Storage |
| 5 MB upload limit | **No** (platform cap 4.5 MB incl. multipart overhead) | Lower app limit to **4 MB**, or implement direct-to-Storage signed uploads (more JS; not recommended initially) |
| `storage/logs/php.log` | **No** | `error_log` to stderr (Vercel collects stdout/stderr) |
| `.env` file | Not needed | `Env::get()` already prefers real environment variables [V]; use Vercel env vars |
| Persistent DB connections | n/a (app uses none) | Good: use the Supabase **pooler** |
| `$_SERVER['HTTPS']`, `REMOTE_ADDR` | **Partially** | See S4, S6 |
| Long-running PHP, cron, workers, WebSockets, queues | None exist [V] | Nothing to redesign |
| CLI tools in `bin/` | Not on Vercel | Run locally/CI against Supabase (direct connection is IPv6; use the session pooler on IPv4-only networks [W]) |
| Runtime DB migrations (`Migrator`) | **No** (don't run migrations in request path or container start) | Supabase CLI in GitHub Actions |
| First-admin installer (`setup.php`) | **No** | One-off script run from a trusted machine |
| Asset versioning `?v=filemtime` | Yes | mtime comes from the image build; cache-busting works per deploy [I] |
| Cold starts after idle | Acceptable but real | First request after scale-to-zero is slower [W]; magnitude **[U]**; mitigate with CDN cache headers (optional, §12) |
| Chatty DB (15–20 queries/page) | Works, but latency-sensitive | Put the Vercel function region next to the Supabase region [I]; consider short `s-maxage` caching on public GET pages (optional) |

### 9.3 Runtime options compared

| Option | Verdict |
|---|---|
| **A. Container Runtime + FrankenPHP (recommended)** | Keeps the PHP codebase, full control of extensions (`pdo_pgsql`, `gd`, `exif`, `curl`), official Vercel guides. **Risk:** Beta. Cost: normal Function pricing (Active CPU) |
| B. `vercel-php` community runtime | Possible, but each route is a function with a `vercel.json` `functions` map; the app's single front controller + rewrite model, `gd`/`pdo_pgsql`/`fileinfo` availability, and PHP 8.3 cap are **[U]**; community-maintained. Use only if Container Beta is unacceptable |
| C. Rewrite in Next.js | Contradicts "no unnecessary rewrites"; the app is small (~2.9 K LOC) so it is feasible, but it is the highest-risk path and unnecessary if A works. Fallback only |

**Plan note [U]:** Vercel's Hobby tier is historically limited to personal/non-commercial use. Klyval is a studio's business site, so **confirm the current terms; a Pro team is likely required.**

---

## 10. Supabase architecture

### 10.1 Which Supabase features are actually needed

| Feature | Need? | Rationale |
|---|---|---|
| **PostgreSQL** | **Yes** | The whole app is relational CRUD |
| **Storage** | **Yes** | Replaces `public/uploads` (10 images today); public bucket for reads, service-key writes from PHP |
| **Row Level Security** | **Yes (deny-by-default)** | Not as the app's authorization layer, but because Supabase auto-exposes tables over the Data API (S2) |
| **Database functions/triggers** | Minimal | One `set_updated_at()` trigger function to replace MySQL `ON UPDATE CURRENT_TIMESTAMP` |
| **Supabase Auth** | **No (at cutover)** | See §5.2 |
| **Edge Functions** | **No** | No logic needs to run at the edge; PHP handles everything |
| **Realtime** | **No** | |
| **pg_cron** | Optional | Purge expired `sessions` and old `login_attempts`; both can also be cleaned probabilistically in-request |
| **Database webhooks / Edge Function for enquiry email** | Optional, later | Only if notification emails are wanted |

### 10.2 Connectivity
- **Application runtime (Vercel):** Supabase **shared pooler, transaction mode, port 6543** (serverless clients) with `sslmode=require`; username form `<role>.<project-ref>` [W]. Transaction mode **does not support prepared statements**, so PDO must use client-side prepares [W].
- **Migrations/CLI/data import:** direct connection (IPv6) or session-mode pooler port 5432 for IPv4-only networks [W].
- Use **two Supabase projects**: `klyval-staging` (Vercel Preview) and `klyval-prod` (Vercel Production). Free-plan projects **pause after ~1 week of inactivity** and offer 500 MB DB / 1 GB storage [W, third-party pricing summaries from May–July 2026; **confirm on supabase.com/pricing**]. A production business site should be on a paid plan; also confirm backup/PITR coverage for the chosen plan **[U]**.

### 10.3 Storage design
- Bucket `media`, **public read**, file size limit 4 MB, allowed MIME `image/jpeg, image/png, image/webp`.
- Object key = existing `media.filename` (e.g. `<32hex>.jpg`), so **no DB row rewrites** are needed when importing existing images.
- PHP flow (unchanged validation + GD re-encode, then upload bytes instead of `imagejpeg($img,$path)`): encode to a temp string/file → `POST {SUPABASE_URL}/storage/v1/object/media/<filename>` with the service-role key; delete via the matching `DELETE`. **Verify exact endpoint/headers against the current Storage API docs during implementation [U].**
- Public URL = `{SUPABASE_URL}/storage/v1/object/public/media/<filename>`; expose through one `media_url()` helper (and `MEDIA_BASE_URL` override for a custom domain/CDN).
- Zero-code-change fallback for the first preview only: a Vercel **external rewrite** `/uploads/:path*` → the public Storage URL. It keeps `'self'` CSP and `url()` working but proxies image traffic through Vercel; behavior of CDN caching on that rewrite is **[U]**. Prefer the helper approach for production.

### 10.4 Proposed Postgres schema
This is the destination schema (equivalent to migrations 001–012). The implementer should split it into ordered files under `supabase/migrations/` (§14.4). Decisions are embedded: identity columns **BY DEFAULT** (importable IDs), `smallint` 0/1 flags (no PHP query rewrites), `text + CHECK` instead of enums, `citext` for case-insensitive keys, `timestamptz`, `updated_at` trigger that fires only on real changes.

```sql
-- ===== 0001_foundation.sql =====
create extension if not exists citext;

create or replace function public.set_updated_at() returns trigger
language plpgsql set search_path = '' as $$
begin new.updated_at := now(); return new; end $$;

create table public.admin_users (
  id            integer generated by default as identity primary key,
  name          varchar(120) not null,
  email         citext       not null unique,
  password_hash varchar(255) not null,
  role          text         not null default 'editor' check (role in ('admin','editor')),
  is_active     smallint     not null default 1 check (is_active in (0,1)),
  last_login_at timestamptz,
  created_at    timestamptz  not null default now(),
  updated_at    timestamptz  not null default now()
);

create table public.site_settings (
  setting_key   varchar(80) primary key,
  setting_value text,
  updated_at    timestamptz not null default now()
);
create table public.homepage_settings (
  setting_key   varchar(60) primary key,
  setting_value text,
  updated_at    timestamptz not null default now()
);

-- ===== 0002_media_vocab.sql =====
create table public.media (
  id            integer generated by default as identity primary key,
  filename      varchar(80)  not null unique,
  original_name varchar(255),
  mime          varchar(40)  not null,
  width         integer      not null,
  height        integer      not null,
  bytes         integer      not null,
  alt_text      varchar(250),
  uploaded_by   integer references public.admin_users(id) on delete set null,
  created_at    timestamptz  not null default now(),
  updated_at    timestamptz  not null default now()
);
create index idx_media_created  on public.media (created_at, id);
create index idx_media_uploader on public.media (uploaded_by);

create table public.technologies (
  id integer generated by default as identity primary key,
  name citext not null unique, slug varchar(80) not null unique,
  created_at timestamptz not null default now());
create table public.skills (
  id integer generated by default as identity primary key,
  name citext not null unique, slug varchar(80) not null unique,
  created_at timestamptz not null default now());

-- ===== 0003_projects.sql =====
create table public.projects (
  id              integer generated by default as identity primary key,
  slug            varchar(120) not null unique,
  name            varchar(160) not null,
  client_name     varchar(160),
  kind            text not null default 'client' check (kind in ('client','product','experiment')),
  description     text,
  case_study      text,
  cover_media_id  integer references public.media(id) on delete set null,
  video_url       varchar(500),
  project_url     varchar(500),
  status          text not null default 'draft' check (status in ('draft','published','archived')),
  featured        smallint not null default 0 check (featured in (0,1)),
  display_order   integer  not null default 0 check (display_order >= 0),
  seo_title       varchar(160),
  seo_description varchar(255),
  is_placeholder  smallint not null default 0 check (is_placeholder in (0,1)),
  published_at    timestamptz,
  created_at      timestamptz not null default now(),
  updated_at      timestamptz not null default now()
);
create index idx_projects_listing  on public.projects (status, display_order, id);
create index idx_projects_featured on public.projects (featured, status, display_order, id);
create index idx_projects_kind     on public.projects (kind, status, display_order, id);
create index idx_projects_cover    on public.projects (cover_media_id);

create table public.project_slug_history (
  id integer generated by default as identity primary key,
  project_id integer not null references public.projects(id) on delete cascade,
  old_slug varchar(120) not null unique, created_at timestamptz not null default now());
create index idx_psh_project on public.project_slug_history (project_id);

create table public.project_media (
  project_id integer not null references public.projects(id) on delete cascade,
  media_id   integer not null references public.media(id)    on delete cascade,
  display_order integer not null default 0, caption varchar(250),
  primary key (project_id, media_id));
create index idx_pm_order on public.project_media (project_id, display_order, media_id);
create index idx_pm_media on public.project_media (media_id);

create table public.project_technologies (
  project_id    integer not null references public.projects(id)     on delete cascade,
  technology_id integer not null references public.technologies(id) on delete restrict,
  primary key (project_id, technology_id));
create index idx_ptech_technology on public.project_technologies (technology_id);

-- ===== 0004_team.sql =====
create table public.team_members (
  id                integer generated by default as identity primary key,
  slug              varchar(120) not null unique,
  name              varchar(120) not null,
  role              varchar(120) not null,
  portrait_media_id integer references public.media(id) on delete set null,
  short_bio         varchar(280),
  full_bio          text,
  portfolio_url     varchar(500),
  featured          smallint not null default 0 check (featured in (0,1)),
  display_order     integer  not null default 0 check (display_order >= 0),
  is_active         smallint not null default 1 check (is_active in (0,1)),
  is_placeholder    smallint not null default 0 check (is_placeholder in (0,1)),
  created_at        timestamptz not null default now(),
  updated_at        timestamptz not null default now()
);
create index idx_team_listing  on public.team_members (is_active, display_order, id);
create index idx_team_featured on public.team_members (featured, is_active, display_order, id);
create index idx_team_portrait on public.team_members (portrait_media_id);

create table public.team_slug_history (
  id integer generated by default as identity primary key,
  member_id integer not null references public.team_members(id) on delete cascade,
  old_slug varchar(120) not null unique, created_at timestamptz not null default now());
create index idx_tsh_member on public.team_slug_history (member_id);

create table public.team_socials (
  id integer generated by default as identity primary key,
  member_id integer not null references public.team_members(id) on delete cascade,
  platform text not null check (platform in ('x','instagram','linkedin','github','behance','dribbble','youtube','tiktok','website')),
  url varchar(500) not null, display_order integer not null default 0,
  unique (member_id, platform));

create table public.team_skills (
  member_id integer not null references public.team_members(id) on delete cascade,
  skill_id  integer not null references public.skills(id)       on delete restrict,
  primary key (member_id, skill_id));
create index idx_tskill_skill on public.team_skills (skill_id);

create table public.project_team (
  project_id integer not null references public.projects(id)     on delete cascade,
  member_id  integer not null references public.team_members(id) on delete cascade,
  role_label varchar(120), display_order integer not null default 0,
  primary key (project_id, member_id));
create index idx_pteam_member on public.project_team (member_id, project_id);

-- ===== 0005_services.sql =====
create table public.services (
  id              integer generated by default as identity primary key,
  slug            varchar(120) not null unique,
  name            varchar(120) not null,
  pillar          text not null check (pillar in ('build','create','launch')),
  description     text,
  visual_media_id integer references public.media(id) on delete set null,
  show_on_home    smallint not null default 0 check (show_on_home in (0,1)),
  display_order   integer  not null default 0 check (display_order >= 0),
  is_active       smallint not null default 1 check (is_active in (0,1)),
  is_placeholder  smallint not null default 0 check (is_placeholder in (0,1)),
  created_at      timestamptz not null default now(),
  updated_at      timestamptz not null default now()
);
create index idx_services_listing on public.services (pillar, is_active, display_order, id);
create index idx_services_home    on public.services (show_on_home, is_active, display_order, id);
create index idx_services_visual  on public.services (visual_media_id);

create table public.service_features (
  id integer generated by default as identity primary key,
  service_id integer not null references public.services(id) on delete cascade,
  label varchar(200) not null, display_order integer not null default 0);
create index idx_sf_service on public.service_features (service_id, display_order, id);

create table public.project_services (
  project_id integer not null references public.projects(id) on delete cascade,
  service_id integer not null references public.services(id) on delete cascade,
  primary key (project_id, service_id));
create index idx_pserv_service on public.project_services (service_id, project_id);

-- ===== 0006_site.sql =====
create table public.homepage_stats (
  id integer generated by default as identity primary key,
  label varchar(80) not null, value varchar(40) not null,
  display_order integer not null default 0,
  is_active smallint not null default 1 check (is_active in (0,1)),
  created_at timestamptz not null default now(), updated_at timestamptz not null default now());
create index idx_stats_listing on public.homepage_stats (is_active, display_order, id);

create table public.social_links (
  id integer generated by default as identity primary key,
  platform text not null unique check (platform in ('x','instagram','linkedin','github','behance','dribbble','youtube','tiktok')),
  url varchar(500) not null, display_order integer not null default 0,
  is_active smallint not null default 1 check (is_active in (0,1)),
  created_at timestamptz not null default now(), updated_at timestamptz not null default now());
create index idx_social_listing on public.social_links (is_active, display_order, id);

create table public.site_media (
  slot text primary key check (slot in ('logo','og_image','favicon')),
  media_id integer references public.media(id) on delete set null,
  updated_at timestamptz not null default now());
create index idx_site_media on public.site_media (media_id);

-- ===== 0007_enquiries_security.sql =====
create table public.contact_submissions (
  id           integer generated by default as identity primary key,
  name         varchar(120) not null,
  email        varchar(190) not null,
  company      varchar(160),
  project_type varchar(40)  not null,
  budget       varchar(80),
  message      text not null,
  notes        text,
  status       text not null default 'new' check (status in ('new','reviewing','contacted','in_progress','closed')),
  ip_hash      char(64),
  created_at   timestamptz not null default now(),
  updated_at   timestamptz not null default now()
);
create index idx_status_created on public.contact_submissions (status, created_at);
create index idx_cs_ip          on public.contact_submissions (ip_hash, created_at);

create table public.login_attempts (
  id         bigint generated by default as identity primary key,
  ip_hash    char(64) not null, email_hash char(64),
  succeeded  smallint not null default 0 check (succeeded in (0,1)),
  created_at timestamptz not null default now());
create index idx_la_ip      on public.login_attempts (ip_hash, succeeded, created_at);
create index idx_la_email   on public.login_attempts (email_hash, succeeded, created_at);
create index idx_la_created on public.login_attempts (created_at);

-- ===== 0008_articles.sql =====
create table public.articles (
  id              integer generated by default as identity primary key,
  slug            varchar(120) not null unique,
  title           varchar(200) not null,
  excerpt         varchar(300),
  content         text,
  cover_media_id  integer references public.media(id) on delete set null,
  author_id       integer references public.team_members(id) on delete set null,
  category        text not null default 'studio' check (category in ('studio','build','create','launch')),
  status          text not null default 'draft'  check (status in ('draft','published','archived')),
  published_at    timestamptz,
  seo_title       varchar(160),
  seo_description varchar(255),
  created_at      timestamptz not null default now(),
  updated_at      timestamptz not null default now()
);
create index idx_articles_listing on public.articles (status, published_at, id);
create index idx_articles_cover   on public.articles (cover_media_id);
create index idx_articles_author  on public.articles (author_id);

create table public.article_slug_history (
  id integer generated by default as identity primary key,
  article_id integer not null references public.articles(id) on delete cascade,
  old_slug varchar(120) not null unique, created_at timestamptz not null default now());
create index idx_ash_article on public.article_slug_history (article_id);

-- ===== 0009_sessions.sql =====
create table public.sessions (
  id         varchar(128) primary key,
  data       text not null,
  expires_at timestamptz not null);
create index idx_sessions_expires on public.sessions (expires_at);

-- ===== 0010_triggers.sql  (fire only when a row really changes, like MySQL ON UPDATE) =====
do $$ declare t text; begin
  foreach t in array array['admin_users','site_settings','homepage_settings','contact_submissions','media',
    'projects','team_members','services','homepage_stats','social_links','site_media','articles'] loop
    execute format('create trigger trg_%1$s_updated before update on public.%1$s
      for each row when (old.* is distinct from new.*) execute function public.set_updated_at()', t);
  end loop;
end $$;
```

### 10.5 Access-control strategy (RLS + roles)

**Principle:** PHP is the only client of the database. Browsers never receive a Supabase key. The Supabase Data API must therefore expose **nothing**.

```sql
-- ===== 0011_rls_and_roles.sql =====
-- Dedicated application role. Set its password in the Supabase SQL editor, NEVER in a migration file.
do $$ begin
  if not exists (select 1 from pg_roles where rolname = 'klyval_app') then
    create role klyval_app nologin;         -- later: alter role klyval_app login password '<from secret store>';
  end if;
end $$;

grant usage on schema public to klyval_app;
grant select, insert, update, delete on all tables    in schema public to klyval_app;
grant usage, select                  on all sequences in schema public to klyval_app;

-- Lock out the public Data API roles completely.
revoke all on all tables    in schema public from anon, authenticated;
revoke all on all sequences in schema public from anon, authenticated;
revoke all on all functions in schema public from anon, authenticated;
alter default privileges in schema public revoke all on tables    from anon, authenticated;
alter default privileges in schema public revoke all on sequences from anon, authenticated;

-- Enable RLS everywhere; the only policy lets klyval_app through. anon/authenticated get none = deny.
do $$ declare t text; begin
  for t in select tablename from pg_tables where schemaname = 'public' loop
    execute format('alter table public.%I enable row level security', t);
    execute format('create policy app_full_access on public.%I for all to klyval_app using (true) with check (true)', t);
  end loop;
end $$;
```

| Actor | Tables | Storage `media` bucket |
|---|---|---|
| Anonymous browser (anon key) | **No access** | Read objects via public URL only |
| `klyval_app` (PHP via pooler) | CRUD on all public tables (RLS policy `TO klyval_app`) | n/a |
| PHP with service-role key (server only) | n/a | Upload/delete objects |
| Postgres owner / CLI | Migrations only (GitHub Actions) | Bucket creation |

Authorization **inside the app stays as is**: `Auth` (signed in) and `AdminOnly` (role) on routes. Optional defense in depth: disable or restrict the project's Data API for the `public` schema in Supabase settings (exact setting name **[U]**, confirm in dashboard).

### 10.6 Session storage design
Implement `SessionHandlerInterface` over `public.sessions`: `read` returns `data` if `expires_at > now()`; `write` upserts with `expires_at = now() + interval '8 hours'` (admin) / session lifetime constant; `destroy` deletes; `gc` deletes expired rows. Register with `session_set_save_handler($h, true)` *before* `session_start()` in `Session::start()`. The `contact_t`/`contact_last` anti-spam keys and the CSRF token live in the same session and keep working. Add `ini_set('session.gc_probability', …)` or a pg_cron job. Note this adds 1 read + 1 write per request that touches the session; the public site starts a session on every request today [V], so consider starting it lazily (only on `/contact`, admin routes, and POSTs) as an **optional** optimization [I].

---

## 11. GitHub and deployment

### 11.1 Current Git state [V]
**None visible.** The archive has no `.git` directory, so branches, history, remotes and whether a repository already exists are **[U]**. `.gitignore` exists and excludes `.env`, `storage/logs/*`, `storage/uploads/*` and **all of `public/uploads/*`** (except `.htaccess`/`index.html`). Consequence: **uploaded images were never meant to be versioned**, so they must be moved to Storage out-of-band, not via Git.

### 11.2 Recommended repository layout (private GitHub repo)
```
.
├── app/  routes/  views/  public/        # unchanged application code (public/uploads removed)
├── bin/                                   # create-admin.php, purge-placeholders.php (adapted)
├── supabase/
│   ├── config.toml                        # from `supabase init`
│   └── migrations/                        # 0001…0011 from §10.4/§10.5
├── scripts/migrate-data/                  # one-off MySQL→Postgres + media upload tooling (not deployed)
├── qa/                                    # kept, excluded from the image
├── .github/workflows/                     # ci.yml, db-migrate.yml
├── Dockerfile.vercel   Caddyfile   vercel.json   .dockerignore
├── .env.example                           # names only, no values
└── README.md  docs/DEPLOY.md
```
Initial commit = the **untouched** current code (minus `.env`, `public/uploads/*`) tagged `legacy-infinityfree`, so every later change is a reviewable diff and rollback is trivial.

### 11.3 Build and runtime
- **No build command, no install command, no output directory.** The "build" is `docker build -f Dockerfile.vercel .`, executed by Vercel on every deploy [W].
- Local: `vercel dev -L` (needs Docker) [W].
- Project settings: framework preset **Container** (a root `Dockerfile.vercel` selects it automatically, or `vercel project update --framework container --yes`) [W]. Region: choose the one closest to the Supabase region **[U: exact setting for container services]**.

### 11.4 Environment variables by environment

| Variable | Production | Preview | Development | Notes |
|---|---|---|---|---|
| `APP_DEBUG` | `0` | `0` | `1` | Never `1` on a public URL |
| `APP_URL` | `https://<prod-domain>` | preview URL or fixed staging domain | `http://localhost:3000` | No trailing slash |
| `APP_TZ` | `UTC` | `UTC` | `UTC` | |
| `APP_KEY` | random 32+ bytes | different random | any | **Sensitive**; distinct per environment |
| `TRUSTED_PROXY` | per §8 S6 verification | same | `0` | |
| `DB_HOST` / `DB_PORT` / `DB_NAME` / `DB_USER` / `DB_PASS` / `DB_SSLMODE` | prod pooler (`6543`), role `klyval_app.<ref>` | **staging** pooler | local/staging | **Sensitive**; Preview must never point at prod |
| `SUPABASE_URL` | prod | staging | staging | |
| `SUPABASE_SERVICE_ROLE_KEY` | prod | staging | staging | **Sensitive, server-only** |
| `MEDIA_BUCKET` | `media` | `media` | `media` | |
| `MEDIA_BASE_URL` | optional | optional | optional | Defaults to the public Storage URL |
| `CONTACT_EMAIL` | optional fallback | optional | optional | |

**GitHub Actions secrets:** `SUPABASE_ACCESS_TOKEN`, `SUPABASE_DB_PASSWORD_PROD`, `SUPABASE_DB_PASSWORD_STAGING`, `SUPABASE_PROJECT_ID_PROD`, `SUPABASE_PROJECT_ID_STAGING`. **Do not** put runtime app secrets in GitHub; they live only in Vercel.

### 11.5 Recommended GitHub → Vercel workflow
1. `main` is protected (PR + passing checks required); work happens on feature branches.
2. **Pull request:** Vercel builds a **Preview Deployment** wired to the **staging** Supabase project [W: previews per PR]. GitHub Actions `ci.yml` runs: `php -l` on every PHP file; `docker build -f Dockerfile.vercel .`; applies `supabase/migrations` to a throw-away local Postgres (`supabase start` / `db reset`) to prove the migrations apply cleanly; runs the new automated smoke tests (§12.4).
3. **Merge to `main`:** `db-migrate.yml` runs `supabase db push` against **production** (path filter `supabase/migrations/**`), and Vercel deploys to Production. Because Vercel deploys on push independently of Actions, **write migrations expand/contract (additive first, destructive in a later release)** so code and schema never have to flip atomically.
4. Secrets rotate in Vercel/Supabase dashboards; no secret is ever committed.
5. Custom domain attached in Vercel; `APP_URL` set to it; DNS cutover per §12.5.

### 11.6 Container files (starting points, validate in implementation)

`vercel.json` (shape from Vercel's PHP/Docker guide [W]):
```json
{
  "$schema": "https://openapi.vercel.sh/vercel.json",
  "services": { "app": { "root": ".", "entrypoint": "Dockerfile.vercel", "runtime": "container" } },
  "rewrites": [ { "source": "/(.*)", "destination": { "service": "app" } } ]
}
```

`Dockerfile.vercel`:
```dockerfile
FROM dunglas/frankenphp:1-php8.3-bookworm
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
 && install-php-extensions pdo_pgsql gd exif
# mbstring, fileinfo, iconv, curl are expected in the base image: confirm with `php -m` during build
WORKDIR /app
COPY app    ./app
COPY routes ./routes
COPY views  ./views
COPY public ./public
COPY Caddyfile /etc/frankenphp/Caddyfile
ENV PORT=80 APP_DEBUG=0
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]
```

`Caddyfile` (Vercel's documented baseline plus the Apache rules that must be re-created):
```
:{$PORT:80} {
    root * /app/public
    encode zstd gzip

    # re-create the .htaccess dotfile rule (allow /.well-known)
    @dotfiles path_regexp ^/\.(?!well-known)
    respond @dotfiles 404

    # re-create assets/.htaccess caching (URLs are versioned with ?v=)
    @assets path /assets/*
    header @assets Cache-Control "public, max-age=31536000, immutable"

    file_server
    php_server {
        try_files {path} index.php
    }

    # never execute stray .php files; only the front controller via rewrite
    @phpFile path *.php*
    error @phpFile "Not found" 404
}
```
`.dockerignore`: `.git`, `.env*`, `qa`, `docs`, `bin`, `scripts`, `supabase`, `deploy`, `*.md`, `public/uploads`.

---

## 12. Migration roadmap

### 12.1 Required changes, in priority order

| P | Task | Why | Risk |
|---|---|---|---|
| P0-1 | Get a full MySQL dump, a copy of `public/uploads`, the MySQL timezone and row counts; answer §13.5 questions | Data migration cannot be planned without data | — |
| P0-2 | **Rotate the DB password** that was in the shared `.env`; stop sharing `.env` | S1 | Low |
| P0-3 | Create private GitHub repo + baseline commit/tag; Supabase **staging** and **prod** projects; Vercel project (confirm plan) | Foundation | Low |
| P1-4 | Postgres migrations 0001–0011 applied to staging | Schema (§10.4–10.5) | Medium |
| P1-5 | **Port DB layer and every MySQL-ism** (§4.7) | App will not run otherwise; silent failure mode (§4.6) | **High** |
| P1-6 | DB-backed sessions; HTTPS + client-IP handling | Sessions/auth/throttles break otherwise | **High** |
| P1-7 | Storage-backed media (+ `media_url()`, CSP, `url()`, 4 URL builders, 4 MB limit, dashboard checks) | Uploads/images break otherwise | **High** |
| P1-8 | Container packaging, stderr logging, `APP_KEY` enforcement | Deployability, S3 | Medium (Beta) |
| P1-9 | Remove InfinityFree/Apache artifacts; new README/`.env.example` | Hygiene, S12 | Low |
| P1-10 | Data import tooling; rehearse on staging until row counts and HTML snapshots match | Protects existing data | **High** |
| P1-11 | CI/CD, env vars, RLS verification, preview sign-off | Repeatable deploys, S2 | Medium |
| P1-12 | Cutover (§12.5) | | **High** |

### 12.2 Optional improvements (do after a stable cutover)
- Add the missing **Sora/Inter woff2** fonts (U1) or drop the preloads.
- **Enquiry notification email** (e.g. Resend from PHP, or a Supabase database webhook) — currently deferred by design.
- Re-check admin `is_active`/`role` per request (S5); admin user-management and password-change UI.
- **Public-page caching:** `Cache-Control: s-maxage=60, stale-while-revalidate` on anonymous GETs to cut DB round-trips and cold-start impact **[U: whether the CDN honors it for container responses]**.
- Lazy session start (only `/contact`, admin, POSTs).
- `boolean` columns, Supabase Auth, pg_cron cleanup, HSTS/`Permissions-Policy` headers.
- Remove dead code (`work.js`, obsolete `qa/*`), regenerate `docs/schema.md`.

### 12.3 High-risk areas

| Area | Why risky | Mitigation |
|---|---|---|
| Silent empty pages | `SiteContent::safe()` and `Settings` swallow SQL errors [V] | During the port, log those exceptions at `error` level to stderr and **fail tests** if any are logged; assert non-empty content in smoke tests |
| Transaction pooler + PDO | No prepared statements [W]; no session-level `SET` persistence | `ATTR_EMULATE_PREPARES=true`; never rely on `SET` or advisory locks |
| Case-sensitivity change | MySQL ci-collation → Postgres | `citext` on 3 columns; check duplicates pre-import |
| Timestamp/timezone shift | Source zone unknown | Measure first (§4.8-2); compare `published_at` of every article/project before/after |
| Container Runtime is Beta | Platform behavior may change [W] | Pin base image tag, keep the Dockerfile portable (it also runs on any container host), fallback options in §9.3 |
| CSP blocks Storage images | `img-src 'self' data:` [V] | Add the media origin; test with the browser console on every template |
| Upload limit | 4.5 MB platform cap [W] | 4 MB app limit, clear message; consider signed direct uploads later |
| RLS misconfiguration | Public data exposure (S2) | Automated anon-key probe in CI/preview |
| Client-IP semantics | S6 | Header echo test in preview before enabling `TRUSTED_PROXY` |
| Cold starts / chatty DB | 15–20 queries/page | Same-region placement, optional caching; measure TTFB |

### 12.4 Testing requirements
**Automated (add to CI; adapt `qa/smoke.sh` ideas):** every public route returns the expected status (200/301/404/503) with **non-empty rendered content** and no `SQLSTATE`/stack-trace text; every admin GET returns 200 when signed in and 302 when not; POSTs without CSRF return 419; contact form valid/invalid/honeypot/rate-limit paths; login success/failure/throttle (429) paths; upload accept (JPEG/PNG/WebP) and reject (wrong type, >4 MB, tiny, huge pixels); media delete blocked when in use.
**Manual in Preview:** two consecutive requests land on different instances and the admin stays logged in (DB sessions); cookie flags (`Secure`, `HttpOnly`, `SameSite=Lax`); image display + CSP console clean; OG image and JSON-LD absolute URLs; `/sitemap.xml`, `/robots.txt`; slug 301s; maintenance mode on/off; all intro/transition/loop interactions on desktop and a phone; Lighthouse pass; client-IP header echo.
**Security:** anon-key REST `select`/`insert` against **every** table must fail/return nothing; service-role key absent from HTML/JS/logs; direct `.php`/dotfile requests return 404.
**Data parity:** per-table counts and id ranges; HTML snapshot diff of every public URL (normalizing `?v=` and timestamps) between old site and new Preview; admin list screens compared; rollback drill.

### 12.5 Cutover strategy (minimal downtime, data protected)
Public content changes only through the admin, and the only public write is the contact form. The app already has a **maintenance mode** that returns 503 to all non-admin requests **including `POST /contact`** [V], which gives a clean write-freeze.

1. **Days before:** lower DNS TTL; finish rehearsals on staging until two consecutive full runs pass all parity checks; prod Supabase has schema + RLS applied; Vercel Production deployed on the `*.vercel.app` URL pointing at prod Supabase but **empty/unlisted**.
2. **T-0:** turn on maintenance mode on the old site (admins can still log in). Re-export the MySQL dump and **diff the uploads folder** for new files.
3. Import data into prod Supabase (preserving IDs, resetting sequences), upload the new/changed images to Storage, run the parity checks against the `*.vercel.app` URL.
4. Attach the custom domain / flip DNS to Vercel; verify HTTPS, `APP_URL`, sitemap, login, a test enquiry (then delete it).
5. Keep the **old InfinityFree site and its MySQL untouched and in maintenance mode** for a rollback window (e.g. 1–2 weeks). **Rollback = point DNS back and turn maintenance mode off.** Any enquiries received on the new site during that window must be exported before a rollback.
6. After the window: take a final Postgres backup, rotate secrets, decommission the old host, delete the old DB, and remove the legacy tag only if desired.

Expected downtime: the maintenance window of steps 2–4 (public visitors see a 503 "Back soon" page) plus DNS propagation; **no data-loss window** because writes are frozen.

---

## 13. Implementation handoff

### 13.1 Existing features that must not be lost (checklist)
- [ ] Public pages: Home (all sections + admin toggles), Work (+filter), Project, Studio, Services, Team member, Insights (+article, hidden when empty), Contact, 404/413/500/503
- [ ] Homepage intro (once per session), page transitions, project wall loops, magnetic/cursor/process animations, reduced-motion behavior
- [ ] SEO: canonical, OG/Twitter, JSON-LD (Organization, WebSite, Article, CreativeWork), sitemap (placeholders excluded), robots, `noindex` for placeholders, 301 slug-history redirects (projects, team, articles)
- [ ] Contact: validation, honeypot, 3 s dwell, 30 s gap, 5/h per IP hash, PRG redirect, stored with `ip_hash`
- [ ] Maintenance mode with admin bypass
- [ ] Optional analytics (Plausible/GA4) with CSP allow-list
- [ ] Admin: login/logout, roles (admin/editor), `AdminOnly` on Settings and every hard delete, login throttle (10/IP, 8/account per 15 min)
- [ ] Admin CRUD: Projects (gallery, team roles/order, technologies, services, status, featured, order, SEO, placeholder flag), Team (skills, socials, project assignment, active flag), Services (features, pillar, show-on-home), Insights, Homepage (copy, banner, section toggles, featured picks, stats), Enquiries (status + notes), Media (upload, alt, in-use delete protection), Settings (contact, SEO, analytics, maintenance, socials, logo/OG/favicon)
- [ ] Delete safeguards: archived-only (projects/articles), inactive-only (team/services), typed-slug confirmation
- [ ] Slug generation/uniqueness including history reservation
- [ ] Image pipeline: finfo + `getimagesize` checks, pixel caps, GD re-encode, 2400 px max side, EXIF rotation, random filenames, no SVG
- [ ] Security headers + strict CSP; CSRF on all POSTs; generic login errors with timing equalization
- [ ] CLI utilities: create admin, purge placeholders

### 13.2 Master file change list

**MODIFY**

| File | Change | Reason |
|---|---|---|
| `app/core/Database.php` | pgsql DSN (`DB_PORT`, `DB_SSLMODE`), `EMULATE_PREPARES=true`, add `insert()` with `RETURNING id`, remove MySQL DSN | §4.7 |
| `app/core/Session.php` | register DB handler before `session_start()`; use `is_https()` for `Secure` | §10.6, S4 |
| `app/core/LoginThrottle.php` | `INTERVAL n SECOND` → `make_interval(secs => n)`; cleanup `interval '2 days'` | §4.7 |
| `app/core/Security.php` | client IP per verified Vercel header; **fail closed if `APP_KEY` empty in production** | S3, S6 |
| `app/core/Csp.php` | add media origin to `img-src` | §8.3 |
| `app/helpers/helpers.php` | `url()` passes absolute URLs through; add `is_https()`, `media_origin()`; `media_url()` returns the Storage URL | §8.3 |
| `app/helpers/bootstrap.php` | log to stderr instead of `storage/logs/php.log`; exception handler logs to stderr | §3.5 |
| `app/helpers/forms.php` | `fmedia()` image `src` → `media_url()` | URL builder #3 |
| `app/models/ContactSubmission.php` | `RETURNING id`; `NOW() - INTERVAL n MINUTE` → `make_interval(mins => n)` | §4.7 |
| `app/models/Project.php`, `Article.php`, `TeamMember.php`, `Service.php` | `lastInsertId` → `Database::insert`; `Service`: `FIELD()` → `array_position` (2 places) | §4.7 |
| `app/services/Settings.php` | `ON DUPLICATE KEY` → `ON CONFLICT` (2 places) | §4.7 |
| `app/services/Slug.php` | `INSERT IGNORE` → `ON CONFLICT DO NOTHING` | §4.7 |
| `app/services/Vocabulary.php` | `lastInsertId` → `Database::insert` | §4.7 |
| `app/services/SiteContent.php` | `img()` → `media_url`-based; log swallowed exceptions to stderr at error level | URL builder #1, §12.3 |
| `app/services/MediaService.php` | replace disk read/write/delete/scan with Storage REST; `MAX_BYTES` 4 MB; `url()` → Storage URL; remove `dir()`, `strayFiles()` or reimplement as bucket check; `lastInsertId` → insert helper | §10.3 |
| `app/controllers/SettingsController.php` | `ON DUPLICATE KEY` for `social_links` → `ON CONFLICT (platform)` | §4.7 |
| `app/controllers/HomepageAdminController.php` | `FIELD()` → `array_position` | §4.7 |
| `app/controllers/AdminController.php` | replace `uploadsWritable`/`stray` with a Storage/DB health check | §3.5 |
| `app/controllers/SeoController.php` | drop `Allow: /uploads/` (optional) | §8.3 |
| `views/admin/dashboard.php` | remove/replace the two filesystem warnings | U3 |
| `views/admin/media/index.php` | image `src` → `media_url()`; "5 MB" text → "4 MB" | URL builder #4, U4 |
| `app/middleware/Auth.php` | *(optional)* per-request `is_active`/role check | S5 |
| `bin/create-admin.php`, `bin/purge-placeholders.php` | work unchanged after `Database.php` is ported; run from a trusted machine against the session pooler/direct connection | §14.3 |
| `.gitignore` | keep `.env*` ignored (allow `.env.example`); drop uploads rules or keep harmlessly | §11.1 |
| `README.md` | rewrite for GitHub/Vercel/Supabase | stale |

**CREATE**

| File | Purpose |
|---|---|
| `app/core/DbSessionHandler.php` | Postgres session handler (§10.6) |
| `Dockerfile.vercel`, `Caddyfile`, `vercel.json`, `.dockerignore` | Container deployment (§11.6) |
| `.env.example` | Variable names only (§6) |
| `supabase/config.toml`, `supabase/migrations/0001…0011_*.sql` | Schema, RLS, roles, triggers (§10.4–10.5) |
| `scripts/migrate-data/*` | MySQL export → transform → `COPY`/insert; `upload-media.php` (push existing images to Storage); `verify.sh` parity checks |
| `.github/workflows/ci.yml`, `db-migrate.yml` | CI and DB promotion (§11.5) |
| `qa/smoke-pg.sh` (or PHP test runner) | New smoke/security suite (§12.4) |
| `docs/DEPLOY.md` | Runbook incl. rollback |

**REMOVE**

| File/dir | Reason |
|---|---|
| `index.php` (root) | htdocs shim |
| all `.htaccess` (root, `app/`, `bin/`, `config/`, `database/`, `deploy/`, `docs/`, `public/`, `public/assets/`, `public/uploads/`, `qa/`, `routes/`, `storage/`, `views/`) | Apache-only; replaced by Caddyfile + image contents |
| `deploy/` (`infinityfree-setup.php`, `nginx.conf.example`) | Host-specific installer/config (S12) |
| `app/services/Migrator.php`, `bin/migrate.php` | Replaced by Supabase CLI migrations |
| `bin/check-uploads.php` | Verifies PHP-exec protection of local uploads; no longer applicable |
| `database/migrations/*.sql` (MySQL) | Superseded; **keep in Git history/tag**, delete from the working tree after Postgres migrations are verified |
| `public/uploads/*` (10 images) | Move to Storage; never commit |
| `public/assets/js/work.js` | Dead code |
| `config/`, `storage/` (empty folders) | Nothing uses them after logging moves to stderr |
| `.env` | Must never be committed |

### 13.3 Destination architecture
```
Browser ──HTTPS──▶ Vercel (CDN/edge) ──▶ Function: container (FrankenPHP + Caddy, PHP 8.3)
                                          │   app/ routes/ views/ public/ (static assets from image)
                                          ├──▶ Supabase Postgres  (Supavisor pooler :6543, role klyval_app, RLS deny-by-default)
                                          │        tables + sessions + triggers
                                          └──▶ Supabase Storage   (bucket "media", public read; PHP writes with service key)
Images:   Browser ──▶ https://<ref>.supabase.co/storage/v1/object/public/media/<filename>
CI/CD:    GitHub (PR → Vercel Preview + staging Supabase; main → db push to prod + Vercel Production)
Admin:    unchanged custom login (bcrypt, roles) with sessions in Postgres
```

### 13.4 Database schema
See **§10.4** (tables, constraints, indexes, triggers) and **§10.5** (role, grants, RLS). Split into the numbered files shown there.

### 13.5 Unresolved questions / missing information

| # | Question | Blocks |
|---|---|---|
| Q1 | A full **MySQL dump** and a copy of **every file** in the live `public/uploads` (the zip has only 10 and no data) | Data migration |
| Q2 | Live MySQL **version and timezone** (`@@global.time_zone`, `NOW()` vs `UTC_TIMESTAMP()`) | Timestamp correctness |
| Q3 | Does a **GitHub repo/history** already exist? Which account/org? | Repo setup |
| Q4 | **Domain name, DNS provider**, current traffic, is the live site currently on InfinityFree at the `APP_URL` host? | Cutover |
| Q5 | How many **admin users** exist and are they all expected to keep working (hashes will be preserved)? | Auth |
| Q6 | How can data be exported from InfinityFree (phpMyAdmin only? size limits? remote MySQL allowed?) | Data migration |
| Q7 | **Vercel plan** (commercial use) and acceptance of **Container Runtime Beta**; budget for Supabase Pro | Plan/risk |
| Q8 | Preferred **region** (users, Vercel, Supabase) | Latency |
| Q9 | Is analytics (GA4/Plausible) enabled in live Settings? Is **maintenance mode** currently on? | CSP/cutover |
| Q10 | Do you have **Sora and Inter** variable woff2 files (OFL-licensed) to add? | U1 |
| Q11 | Are all 10 uploaded images referenced by `media` rows; any rows whose file is missing? | Data integrity |
| Q12 | Do you want **enquiry email notifications** as part of this migration or later? | Scope |
| Q13 | Which Vercel header carries the real client IP for container functions, and is `X-Forwarded-Proto` set? (answer by a preview test) | S4, S6 |
| Q14 | Exact Supabase Storage REST request/response contract and Data API exposure setting at implementation time | Storage, S2 |

---

## 14. Migration Handoff for OpenCode

> **Audience:** a coding agent implementing the migration without re-auditing. Everything below was derived from the real source (tags in §0). Where something is **[U]**, verify it in the sandbox/preview *before* building on it. **Do not rewrite the application. Do not redesign any view, CSS or JS.** The change set is the file list in §13.2.

### 14.1 Mission and ground rules
**Mission:** move Klyval from InfinityFree (Apache + MySQL + local disk) to **GitHub → Vercel (Container Runtime, FrankenPHP) + Supabase (Postgres + Storage)** with **zero data loss, feature parity, and no secret in Git**.

**Hard rules**
1. Keep the PHP codebase, routes, views, assets and the custom admin login. Smallest diffs that work.
2. **Never read, print, log or commit secret values.** `.env` must not enter the repo or the image. Provide `.env.example` with names only.
3. All SQL values stay parameterized. Dynamic identifiers only from code constants.
4. Preserve **original primary-key IDs** and **`media.filename`** values during data import.
5. Supabase Data API must expose **nothing** (RLS + revoked grants, §10.5). Service-role key is server-only.
6. Migrations are additive/expand-contract and run from CI, never from the app at runtime.
7. After porting, **no swallowed SQL error may go unlogged** (§12.3): log to stderr, and make tests fail on any such log line.

### 14.2 Ordered tasks with acceptance criteria

| ID | Task | Done when |
|---|---|---|
| **T1** | Init Git; baseline commit of the untouched code excluding `.env` and `public/uploads/*`; tag `legacy-infinityfree`; add `.env.example` | `git ls-files` shows no `.env`, no uploads; tag exists |
| **T2** | `supabase init`; create `supabase/migrations/0001…0011` from §10.4/§10.5 (see §14.4); apply to a local Postgres (`supabase start`) and to **staging** | `supabase db reset` succeeds; every table has RLS on; `anon`/`authenticated` have no privileges; `klyval_app` can CRUD |
| **T3** | Port `Database.php` (pgsql DSN, `EMULATE_PREPARES=true`, `insert()` returning id); apply every SQL change in §4.7/§13.2 | `grep -rnE "lastInsertId\|FIELD\(\|ON DUPLICATE\|INSERT IGNORE\|INTERVAL [0-9]\|INTERVAL \"" app bin` returns nothing; app boots against local Postgres |
| **T4** | Add `DbSessionHandler`, `is_https()`; update `Session::start()` | Login persists across requests handled by two container instances; cookie has `Secure; HttpOnly; SameSite=Lax` behind HTTPS |
| **T5** | Storage backend in `MediaService`; `media_file_url()`/`media_origin()`; fix the four URL builders, `url()`, `Csp`; set `MAX_BYTES = 4 MiB`; update dashboard + admin media text | Upload JPEG/PNG/WebP works; image renders on public page **with a clean browser console (no CSP violation)**; delete removes the object; OG/JSON-LD URLs are single, absolute, correct |
| **T6** | stderr logging; `APP_KEY` fail-closed in production | No `storage/logs` references remain; boot without `APP_KEY` in production fails loudly |
| **T7** | `Dockerfile.vercel`, `Caddyfile`, `vercel.json`, `.dockerignore`; `docker build` + `vercel dev -L` locally | `curl /` 200; `curl /index.php` and `/.env` and `/app/core/Router.php` all 404; `/assets/css/base.css` has the immutable cache header; `php -m` lists `pdo_pgsql gd mbstring fileinfo iconv curl exif` |
| **T8** | Remove files in §13.2 REMOVE; rewrite README; add `docs/DEPLOY.md` | Repo has no `.htaccess`, `deploy/`, `Migrator`; README matches reality |
| **T9** | Data tooling in `scripts/migrate-data/` (§14.6); rehearse on staging | Row counts, id ranges and a column checksum match for all 24 data tables; sequences reset; all images load |
| **T10** | GitHub Actions (`ci.yml`, `db-migrate.yml`); Vercel project, env vars per §11.4, Preview → staging | PR produces a working Preview; CI is green; `main` migration job works against a scratch project first |
| **T11** | Automated smoke + security suite (§12.4), including the **anon-key RLS probe** and the **"no logged swallowed SQL errors"** assertion | Suite green in Preview |
| **T12** | Verify **[U]** items in Preview: client-IP header, `X-Forwarded-Proto`, region/latency, cold-start, `getenv()` visibility, Storage API contract; decide `TRUSTED_PROXY` | Findings written into `docs/DEPLOY.md`; code adjusted |
| **T13** | Production cutover per §12.5 with the owner | Parity checks pass; rollback path documented and tested |

### 14.3 Admin bootstrap and CLI
- **`bin/create-admin.php` and `bin/purge-placeholders.php` need no code change** once `Database.php` is ported (they use plain INSERT/SELECT/DELETE). Run them from a trusted machine with `DB_*` pointing at the **session pooler (5432)** or direct connection and a role allowed to write `admin_users` (`klyval_app`). They need `pdo_pgsql` locally (or run via `docker run --env-file … <image> php bin/create-admin.php …`).
- **Existing admins migrate with their `password_hash` intact**, so no password reset is needed. Create a *new* admin only if `admin_users` is empty after import.
- Remove `deploy/infinityfree-setup.php` and `bin/migrate.php`. Do **not** build a web installer for Vercel.

### 14.4 SQL migration files (split of §10.4/§10.5)
`0001_foundation` (extension, `set_updated_at`, `admin_users`, settings tables) → `0002_media_vocab` → `0003_projects` → `0004_team` → `0005_services` → `0006_site` → `0007_enquiries_security` → `0008_articles` → `0009_sessions` → `0010_triggers` → `0011_rls_and_roles`. Keep the SQL exactly as in §10.4/§10.5 unless a test proves a problem. Add `0012_storage.sql` after verifying the Storage schema in the Supabase version in use **[U]**:
```sql
insert into storage.buckets (id, name, public, file_size_limit, allowed_mime_types)
values ('media','media', true, 4194304, array['image/jpeg','image/png','image/webp'])
on conflict (id) do nothing;
```
Any **future** table needs: `enable row level security`, `grant … to klyval_app`, and the `app_full_access` policy; add that to the migration template.

### 14.5 Code sketches for the non-obvious changes
*(Sketches, not final code: adapt style to the codebase, keep `declare(strict_types=1)` conventions.)*

**`Database.php`**
```php
$dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s;sslmode=%s',
    Env::get('DB_HOST', '127.0.0.1'), Env::get('DB_PORT', '6543'),
    Env::get('DB_NAME', 'postgres'), Env::get('DB_SSLMODE', 'require'));
self::$pdo = new PDO($dsn, Env::get('DB_USER', ''), Env::get('DB_PASS', ''), [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => true,   // transaction-mode pooler has no server-side prepared statements
]);                                        // do NOT rely on session-level SET (lost between transactions)
public static function insert(string $sql, array $params = []): int {
    return (int) self::query($sql . ' RETURNING id', $params)->fetchColumn();
}
```
Replace e.g. `Database::query('INSERT …', $v); $id = (int)Database::pdo()->lastInsertId();` with `$id = Database::insert('INSERT …', $v);`.

**SQL dialect one-liners**
```php
// Service::adminList / options, HomepageAdminController
"… ORDER BY array_position(ARRAY['build','create','launch'], s.pillar), s.display_order, s.id"
// Settings::save / setMedia / SettingsController social_links
"INSERT INTO $table (setting_key, setting_value) VALUES (?, ?)
 ON CONFLICT (setting_key) DO UPDATE SET setting_value = EXCLUDED.setting_value"
"INSERT INTO site_media (slot, media_id) VALUES (?, ?) ON CONFLICT (slot) DO UPDATE SET media_id = EXCLUDED.media_id"
"INSERT INTO social_links (platform,url,display_order,is_active) VALUES (?,?,?,?)
 ON CONFLICT (platform) DO UPDATE SET url=EXCLUDED.url, display_order=EXCLUDED.display_order, is_active=EXCLUDED.is_active"
// Slug::recordChange
"INSERT INTO $histTable ($histCol, old_slug) VALUES (?, ?) ON CONFLICT DO NOTHING"
// LoginThrottle / ContactSubmission (interpolate only the int constant)
"… created_at >= (now() - make_interval(secs => " . (int)self::WINDOW . "))"
"… created_at >= (now() - make_interval(mins => " . (int)$minutes . "))"
"DELETE FROM login_attempts WHERE created_at < (now() - interval '2 days')"
```

**`helpers.php`**
```php
function is_https(): bool {   // trust X-Forwarded-Proto only because the container is reachable solely through Vercel [verify T12]
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}
function media_base(): string {   // public URL prefix for stored images, no trailing slash
    $b = Env::get('MEDIA_BASE_URL');
    if ($b) return rtrim($b, '/');
    $s = Env::get('SUPABASE_URL');
    return $s ? rtrim($s, '/') . '/storage/v1/object/public/' . (Env::get('MEDIA_BUCKET') ?: 'media') : '';
}
function media_origin(): string { $p = parse_url(media_base()); return isset($p['scheme'], $p['host']) ? $p['scheme'].'://'.$p['host'].(isset($p['port']) ? ':'.$p['port'] : '') : ''; }
function media_file_url(string $filename): string { return media_base() . '/' . rawurlencode($filename); }
function url(string $path = '/'): string {
    if (preg_match('#^https?://#i', $path)) return $path;      // absolute Storage URLs pass through
    /* …existing body… */
}
```
Use `media_file_url()` in: `MediaService::url()`, `SiteContent::img()`, `forms.php::fmedia()`, `views/admin/media/index.php`. In `Csp::build()`: `$img = "'self' data:" . (($o = media_origin()) ? " $o" : '');`.

**`DbSessionHandler`** (fail-soft: a DB hiccup must not 500 the public site)
```php
final class DbSessionHandler implements SessionHandlerInterface {
    private const TTL = 28800; // 8 h
    public function open(string $p, string $n): bool { return true; }
    public function close(): bool { return true; }
    public function read(string $id): string|false {
        try { $v = Database::query('SELECT data FROM sessions WHERE id = ? AND expires_at > now()', [$id])->fetchColumn();
              return $v === false ? '' : (string)base64_decode((string)$v); }
        catch (Throwable $e) { error_log('session read: '.$e->getMessage()); return ''; }
    }
    public function write(string $id, string $data): bool {
        try { Database::query("INSERT INTO sessions (id, data, expires_at) VALUES (?, ?, now() + make_interval(secs => ".self::TTL."))
              ON CONFLICT (id) DO UPDATE SET data = EXCLUDED.data, expires_at = EXCLUDED.expires_at", [$id, base64_encode($data)]); return true; }
        catch (Throwable $e) { error_log('session write: '.$e->getMessage()); return false; }
    }
    public function destroy(string $id): bool { Database::query('DELETE FROM sessions WHERE id = ?', [$id]); return true; }
    public function gc(int $max): int|false { return Database::query('DELETE FROM sessions WHERE expires_at < now()')->rowCount(); }
}
```
In `Session::start()`: `session_set_save_handler(new DbSessionHandler(), true);` before `session_start()`, and `$https = is_https();`.

**`MediaService` Storage calls** *(verify the REST contract [U] against current Supabase Storage docs)*
```php
// replace imagejpeg($img,$path,85) etc. with in-memory encoding
ob_start(); match ($mime) { 'image/jpeg' => imagejpeg($img, null, 85), 'image/png' => imagepng($img, null, 6), default => imagewebp($img, null, 82) };
$bytes = (string)ob_get_clean(); imagedestroy($img);
if (!self::put($name, $bytes, $mime)) return ['error' => 'The image could not be saved.'];
// …then INSERT INTO media (… bytes = strlen($bytes) …); on DB failure call self::remove($name)

private static function put(string $name, string $bytes, string $mime): bool {
    $key = (string)Env::get('SUPABASE_SERVICE_ROLE_KEY');
    $ch = curl_init(rtrim((string)Env::get('SUPABASE_URL'), '/') . '/storage/v1/object/' . (Env::get('MEDIA_BUCKET') ?: 'media') . '/' . rawurlencode($name));
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $bytes, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer '.$key, 'apikey: '.$key, 'Content-Type: '.$mime, 'x-upsert: false']]);
    $ok = curl_exec($ch) !== false && curl_getinfo($ch, CURLINFO_HTTP_CODE) < 300; curl_close($ch); return $ok;
}
// remove(): same URL with CURLOPT_CUSTOMREQUEST => 'DELETE'. Never log $key or response bodies containing it.
```
Keep `process()`'s validation exactly as is (finfo, `getimagesize`, caps, EXIF rotation, GD re-encode, random name). Update `MAX_BYTES` and the two user-facing "5 MB" strings to 4 MB.

### 14.6 Data migration tooling (`scripts/migrate-data/`)
1. **Inputs:** MySQL dump/CSVs, `public/uploads` copy, measured MySQL timezone (Q1, Q2).
2. **Method:** `pgloader` if the source MySQL is network-reachable **[U]**; otherwise export each table to CSV (UTF-8, explicit NULLs) and load with `\copy` into the §10.4 schema. Don't let a tool create tables; the schema is hand-written.
3. **Load order:** `admin_users, media, technologies, skills, projects, team_members, services, articles, project_slug_history, project_media, project_technologies, team_slug_history, team_socials, team_skills, project_team, service_features, project_services, article_slug_history, homepage_settings, site_settings, homepage_stats, social_links, site_media, contact_submissions`. Skip `login_attempts`, `schema_migrations`. Disable triggers (or load as the owner with `session_replication_role = replica`) so `updated_at` isn't overwritten.
4. **Transforms:** zero dates → `NULL`; timestamps → `timestamptz` using the measured source zone; confirm TINYINT values are only 0/1; trim/validate URL schemes.
5. **After load:** for every identity table run `select setval(pg_get_serial_sequence('public.<t>','id'), coalesce(max(id),1)) from public.<t>;` (use `is_called` correctly when the table is empty).
6. **Media:** `upload-media.php` pushes each file to the `media` bucket under its existing filename; report any `media` row without a file and any file without a row.
7. **Verify:** counts, `min/max(id)`, column checksums, HTML snapshots of all public URLs vs. the old site, admin list screens, and the RLS anon probe. Make the whole pipeline **re-runnable** against an emptied staging DB.

### 14.7 Definition of done
- All §13.1 checklist items verified in a Vercel Preview on staging **and** again on production after cutover.
- CI green; Preview and Production use different Supabase projects; no secret in Git history (`git log -p | grep` for key shapes before first push of the repo).
- `grep` for the MySQL constructs in T3 is empty; no `/uploads/` literals remain in PHP/views; no `storage/logs` references.
- Anon-key probe returns nothing for **every** table and Storage writes; service-role key appears nowhere in responses.
- `docs/DEPLOY.md` documents env vars, migration promotion, cutover, rollback, and the verified answers to Q13/Q14.

### 14.8 Do-not list
- Don't add a framework, ORM, Composer dependency tree, Node build, or rewrite templates/CSS/JS.
- Don't switch to Supabase Auth or convert flags to `boolean` in this migration.
- Don't run migrations from the web process; don't expose any CLI/installer over HTTP.
- Don't point Preview deployments at the production database or Storage bucket.
- Don't weaken the CSP (no `'unsafe-inline'` scripts, no `*`); only add the single media origin.
- Don't delete the old host or MySQL data until the rollback window ends.

*End of report.*
