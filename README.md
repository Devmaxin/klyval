# Klyval — Phases 1–2 (Foundation + Design System)

> **Target platform changed after this README was written.** The site now runs on
> **Vercel (Container Runtime, FrankenPHP, PHP 8.3)** with **Supabase
> (PostgreSQL + Storage)**. The date/local-pattern guides below still describe
> the legacy InfinityFree setup and are kept for the cutover rollback plan.
> For the new deployment see **`docs/DEPLOY.md`**.

1. Point the web root at `public/`. Needs PHP 8.1+ with `pdo_mysql` and `mbstring`, MySQL 5.7+/MariaDB 10.3+, Apache `mod_rewrite` (Nginx: route everything to `public/index.php`).
2. `cp .env.example .env`, fill in DB values and `APP_URL`.
3. `php bin/migrate.php` (safe to re-run; applies only pending migrations, never drops anything).
4. `php bin/create-admin.php you@example.com "Your Name"`
5. Add fonts: `public/assets/fonts/sora-var.woff2` and `inter-var.woff2` (self-hosted variable woff2, latin subset). Fallbacks apply until then.

## Phase 3 — Homepage
- Content: `app/services/HomeContent.php` (placeholder data, DB-shaped). Sections: `views/pages/home/*.php`.
- Intro: markup `views/components/intro.php`, visuals `css/intro.css`, sequencing `js/intro.js`, gate `js/boot.js` (plays once per session, skipped under reduced motion).

## Phase 4 — Content pages
Routes: `/work`, `/work/{slug}`, `/studio`, `/services`, `/team/{slug}`, `/contact` (POST stores to `contact_submissions` — run `php bin/migrate.php`).
Content: `app/services/SiteContent.php` (placeholders, DB-shaped). Insights pages are deferred.

## QA harness
`qa/server.py` serves a static mirror of the homepage markup with the real CSS/JS (`/?n=5` = 5 projects). Needs Python + Playwright; `qa/t_*.py` are the tests used for the Phase 3 checks.

## Phase 5 — Admin (CMS)
- Sign in at `/admin` (create the first admin with `bin/create-admin.php`). Roles: **admin** (everything, incl. Settings, media delete, permanent delete) and **editor** (content only).
- Sections: Dashboard, Projects, Team, Services, Homepage, Enquiries, Media, Settings. Content is plain text (blank line = paragraph); there is no HTML editor.
- No content is seeded. The public site hides empty sections. Placeholder rows (flagged in the admin) can be purged with `php bin/purge-placeholders.php --yes`.
- Schema: `php bin/migrate.php` (migrations 001–010). Map: `docs/schema.md`.
- **Uploads**: `public/uploads/` must be writable by PHP and must never execute code. Apache uses the bundled `.htaccess`. On **Nginx** add: `location ^~ /uploads/ { location ~ \.(php|phtml|phar)$ { return 403; } try_files $uri =404; }` and make sure PHP-FPM is not applied under `/uploads/`.
- PHP extensions: `pdo_mysql`, `mbstring`, `gd` (with JPEG/PNG/WebP), `fileinfo`; `exif` is optional (JPEG rotation).
- Set `upload_max_filesize` and `post_max_size` to at least 8M.

## Hardening and production checklist (Phase 6a)
1. `cp .env.example .env`, set `APP_URL`, DB values, a long random `APP_KEY` (keys the login and contact throttles) and `APP_TZ`. Set `TRUSTED_PROXY=1` ONLY behind your own reverse proxy that appends the client IP to `X-Forwarded-For`.
2. `php bin/migrate.php` (migrations 001–012).
3. Web server: Apache uses the bundled `.htaccess` files. **Nginx: use `deploy/nginx.conf.example`**, whose `^~ /uploads/` block makes PHP execution there impossible. Do not skip it.
4. Prove it on the real server: `php bin/check-uploads.php https://your-site.example` (fails if PHP executes under /uploads/ or stray files exist).
5. On a SCRATCH database only: `bash qa/smoke.sh`, `bash qa/security.sh`, `php qa/upload-test.php`. `python3 qa/audit.py` needs no PHP.
6. PHP: `upload_max_filesize` and `post_max_size` of at least 8M, extensions `pdo_mysql mbstring gd fileinfo` (+ `exif` optional).
7. Login throttle: 10 failures per address or 8 per account in 15 minutes (`login_attempts`). Contact form: 5 per address per hour.

## Insights, SEO, transitions
- `/insights` and `/insights/{slug}` exist only once an article is published (no empty section). Category is a fixed list (Studio, Build, Create, Launch). Tags are deferred.
- `/sitemap.xml` and `/robots.txt` are generated. Placeholder content is excluded from the sitemap and served `noindex`.
- Structured data: Organization + WebSite (home), Article (insights), CreativeWork (projects). Only facts that exist are included.
- Page transitions: `js/transitions.js` + the end of `css/motion.css`. Skipped for reduced motion, modified clicks, new tabs, files, hash links and the admin.

## Deferred on purpose
Admin user-management UI, contact email notifications, article tags, anything that changes the Klyval identity.

## Deploying to InfinityFree (Apache shared hosting, web root = `htdocs`)
InfinityFree serves `htdocs/` as the web root and gives no SSH. Klyval supports that layout without moving `public/`: the root `index.php` only does `require __DIR__ . '/public/index.php'`, and the root `.htaccess` (mod_rewrite only) serves `/assets`, `/uploads` and `/favicon.svg` from `public/`, sends every other URL to the application, and blocks everything internal. `nginx.conf.example` is for a VPS only and is not used on InfinityFree.

### 1. Prepare the account
1. In the InfinityFree control panel set the PHP version to **8.1 or newer** and enable the free **SSL certificate** for your domain (the app marks session cookies Secure automatically on HTTPS).
2. Create a **MySQL database** (Control Panel → MySQL Databases). Note the four values: host (looks like `sqlXXX.infinityfree.com`, NOT `localhost`), database name, user, password.
3. Deploy at the **domain root** (the domain's own `htdocs`), not in a sub-folder.

### 2. Prepare files on your computer
1. Copy `.env.example` to `.env` and fill it in: `APP_URL=https://your-domain` (no trailing slash), `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, `APP_KEY` (long random string), `APP_ENV=production`, `APP_DEBUG=0`, and `SETUP_KEY` (random, 24+ characters, used once).
2. Copy `deploy/infinityfree-setup.php` to a new file named `setup.php` (temporary; you will delete it).

### 3. Upload with FTP (FileZilla etc.; enable "show hidden files" so `.htaccess` and `.env` are included)
Open `htdocs/`, delete the placeholder `index2.html` (and any `index.html`), then upload exactly this:
```
htdocs/
├── .htaccess                 root rules (required)
├── .env                      your real settings (never committed, blocked from the web)
├── index.php                 thin entry point
├── setup.php                 TEMPORARY installer (delete after step 4)
├── app/        (+ .htaccess)
├── bin/        (+ .htaccess)
├── config/     (+ .htaccess)
├── database/   (+ .htaccess)   includes migrations/
├── routes/     (+ .htaccess)
├── views/      (+ .htaccess)
├── storage/    (+ .htaccess)   logs/ and uploads/ inside
└── public/
    ├── .htaccess
    ├── index.php             the real front controller
    ├── favicon.svg
    ├── assets/               css/ js/ images/ fonts/  (put sora-var.woff2 and inter-var.woff2 in fonts/)
    └── uploads/              keep its .htaccess and index.html
```
Do not upload `qa/`, `docs/`, `deploy/`, `README.md`, `.env.example`, `.gitignore` or any `.git` folder (they are not needed; the root `.htaccess` blocks them anyway). If an uploaded folder came through without its `.htaccess`, upload that file again.
If uploads or logging fail, make `public/uploads/` and `storage/logs/` writable (permissions 755, or 775/777 if the host requires it).

### 4. Install (no SSH needed)
1. Open `https://your-domain/setup.php`, enter the `SETUP_KEY`, an admin name, email and a password of 12+ characters, and submit. It applies all migrations and creates the first administrator (it refuses if an admin already exists).
2. **Delete `htdocs/setup.php`** (it tries to delete itself; confirm it is gone) and clear `SETUP_KEY` in `.env`.
3. Sign in at `https://your-domain/admin`, then fill in Settings (contact details, logo, favicon, social links).

### 5. Check the deployment
Open each of these: `/`, `/work`, `/studio`, `/services`, `/contact`, `/admin` (shows the login page), `/sitemap.xml`, `/robots.txt`, `/assets/css/base.css`.
Then confirm protection (each must NOT show file contents): `/.env`, `/app/core/Router.php`, `/config/`, `/database/migrations/001_foundation.sql`, `/views/layouts/main.php`, `/storage/logs/php.log`, `/public/index.php` → all should be **403** (or the site's 404 page).
Prove uploads cannot execute: upload a file `public/uploads/probe.php` containing `<?php echo 'EXECUTED-'.(40+2);`, open `https://your-domain/uploads/probe.php`: you must get **403** and never `EXECUTED-42`. Then delete the probe file. After uploading your first image in Admin → Media, confirm it displays.

### Not verifiable without a live InfinityFree account
Whether the host honours these `.htaccess` rules (mod_rewrite and the AllowOverride level); the PHP version and extensions it offers (`pdo_mysql`, `mbstring`, `gd` with WebP, `fileinfo`); the real database host name; the host's upload size limit (the app allows 5 MB images); what client IP the host reports (if every visitor appears with the same IP the per-address throttles would trip too easily: tell me and I will adjust); and that the one-time `setup.php` runs there. `python3 qa/htaccess_check.py` checks the rule logic against the real files but is not Apache.

## Visual refinement (homepage, Work, project, Services, Studio, team, Contact)
- Intro (`intro.css`, `intro.js`, `boot.js`, `components/intro.php`) is untouched.
- `js/loop.js` drives the homepage project columns (opposite directions) and the team/profile project strips. Only real items are links; clones are aria-hidden and inert. Off under reduced motion and below 700px (wall). The wall is "live" only with 4+ published projects (server flag `data-live`).
- `js/work-filter.js` filters /work in place. The links are real (`/work?kind=…`), canonical stays `/work`, and only kinds that have projects are offered.
- Components: `project-card`, `work-card`, `project-strip`, `team-list`. Image dimensions and alt text come from the existing `media` row (no schema change).
- `qa/server.py` and `qa/t_work.py` target the old pinned horizontal "work" section and are obsolete.
