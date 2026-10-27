# Klyval schema map (generated from database/migrations by qa/lint_migrations.py)

Order everywhere: `display_order ASC, id ASC` (id is the stable tie-breaker).


## admin_users  _(migration 001_foundation.sql)_
columns: id, name, email, password_hash, role, is_active, last_login_at, created_at, updated_at
PK: id
unique: uq_admin_email(email)

## site_settings  _(migration 001_foundation.sql)_
columns: setting_key, setting_value, updated_at
PK: setting_key

## contact_submissions  _(migration 002_contact_submissions.sql)_
columns: id, name, email, company, project_type, budget, message, status, ip_hash, created_at, updated_at
PK: id
indexes: idx_status_created(status, created_at)

## media  _(migration 003_media.sql)_
columns: id, filename, original_name, mime, width, height, bytes, alt_text, uploaded_by, created_at, updated_at
PK: id
unique: uq_media_filename(filename)
indexes: idx_media_created(created_at, id); idx_media_uploader(uploaded_by)
FK fk_media_uploader: uploaded_by → admin_users(id) ON DELETE SET NULL

## technologies  _(migration 004_technologies_skills.sql)_
columns: id, name, slug, created_at
PK: id
unique: uq_technologies_name(name); uq_technologies_slug(slug)

## skills  _(migration 004_technologies_skills.sql)_
columns: id, name, slug, created_at
PK: id
unique: uq_skills_name(name); uq_skills_slug(slug)

## projects  _(migration 005_projects.sql)_
columns: id, slug, name, client_name, kind, description, case_study, cover_media_id, video_url, project_url, status, featured, display_order, seo_title, seo_description, is_placeholder, published_at, created_at, updated_at
PK: id
unique: uq_projects_slug(slug)
indexes: idx_projects_listing(status, display_order, id); idx_projects_featured(featured, status, display_order, id); idx_projects_kind(kind, status, display_order, id); idx_projects_cover(cover_media_id)
FK fk_projects_cover: cover_media_id → media(id) ON DELETE SET NULL

## project_slug_history  _(migration 005_projects.sql)_
columns: id, project_id, old_slug, created_at
PK: id
unique: uq_project_old_slug(old_slug)
indexes: idx_psh_project(project_id)
FK fk_psh_project: project_id → projects(id) ON DELETE CASCADE

## project_media  _(migration 005_projects.sql)_
columns: project_id, media_id, display_order, caption
PK: project_id, media_id
indexes: idx_pm_order(project_id, display_order, media_id); idx_pm_media(media_id)
FK fk_pm_project: project_id → projects(id) ON DELETE CASCADE
FK fk_pm_media: media_id → media(id) ON DELETE CASCADE

## project_technologies  _(migration 005_projects.sql)_
columns: project_id, technology_id
PK: project_id, technology_id
indexes: idx_ptech_technology(technology_id)
FK fk_ptech_project: project_id → projects(id) ON DELETE CASCADE
FK fk_ptech_technology: technology_id → technologies(id) ON DELETE RESTRICT

## team_members  _(migration 006_team.sql)_
columns: id, slug, name, role, portrait_media_id, short_bio, full_bio, portfolio_url, featured, display_order, is_active, is_placeholder, created_at, updated_at
PK: id
unique: uq_team_slug(slug)
indexes: idx_team_listing(is_active, display_order, id); idx_team_featured(featured, is_active, display_order, id); idx_team_portrait(portrait_media_id)
FK fk_team_portrait: portrait_media_id → media(id) ON DELETE SET NULL

## team_slug_history  _(migration 006_team.sql)_
columns: id, member_id, old_slug, created_at
PK: id
unique: uq_team_old_slug(old_slug)
indexes: idx_tsh_member(member_id)
FK fk_tsh_member: member_id → team_members(id) ON DELETE CASCADE

## team_socials  _(migration 006_team.sql)_
columns: id, member_id, platform, url, display_order
PK: id
unique: uq_team_social(member_id, platform)
FK fk_team_social_member: member_id → team_members(id) ON DELETE CASCADE

## team_skills  _(migration 006_team.sql)_
columns: member_id, skill_id
PK: member_id, skill_id
indexes: idx_tskill_skill(skill_id)
FK fk_tskill_member: member_id → team_members(id) ON DELETE CASCADE
FK fk_tskill_skill: skill_id → skills(id) ON DELETE RESTRICT

## project_team  _(migration 006_team.sql)_
columns: project_id, member_id, role_label, display_order
PK: project_id, member_id
indexes: idx_pteam_member(member_id, project_id)
FK fk_pteam_project: project_id → projects(id) ON DELETE CASCADE
FK fk_pteam_member: member_id → team_members(id) ON DELETE CASCADE

## services  _(migration 007_services.sql)_
columns: id, slug, name, pillar, description, visual_media_id, show_on_home, display_order, is_active, is_placeholder, created_at, updated_at
PK: id
unique: uq_services_slug(slug)
indexes: idx_services_listing(pillar, is_active, display_order, id); idx_services_home(show_on_home, is_active, display_order, id); idx_services_visual(visual_media_id)
FK fk_services_visual: visual_media_id → media(id) ON DELETE SET NULL

## service_features  _(migration 007_services.sql)_
columns: id, service_id, label, display_order
PK: id
indexes: idx_sf_service(service_id, display_order, id)
FK fk_sf_service: service_id → services(id) ON DELETE CASCADE

## homepage_settings  _(migration 008_homepage_and_site.sql)_
columns: setting_key, setting_value, updated_at
PK: setting_key

## homepage_stats  _(migration 008_homepage_and_site.sql)_
columns: id, label, value, display_order, is_active, created_at, updated_at
PK: id
indexes: idx_stats_listing(is_active, display_order, id)

## social_links  _(migration 008_homepage_and_site.sql)_
columns: id, platform, url, display_order, is_active, created_at, updated_at
PK: id
unique: uq_social_platform(platform)
indexes: idx_social_listing(is_active, display_order, id)

## site_media  _(migration 008_homepage_and_site.sql)_
columns: slot, media_id, updated_at
PK: slot
indexes: idx_site_media(media_id)
FK fk_site_media: media_id → media(id) ON DELETE SET NULL

## project_services  _(migration 010_project_services.sql)_
columns: project_id, service_id
PK: project_id, service_id
indexes: idx_pserv_service(service_id, project_id)
FK fk_pserv_project: project_id → projects(id) ON DELETE CASCADE
FK fk_pserv_service: service_id → services(id) ON DELETE CASCADE

## login_attempts  _(migration 011_login_attempts.sql)_
columns: id, ip_hash, email_hash, succeeded, created_at
PK: id
indexes: idx_la_ip(ip_hash, succeeded, created_at); idx_la_email(email_hash, succeeded, created_at); idx_la_created(created_at)

## articles  _(migration 012_articles.sql)_
columns: id, slug, title, excerpt, content, cover_media_id, author_id, category, status, published_at, seo_title, seo_description, created_at, updated_at
PK: id
unique: uq_articles_slug(slug)
indexes: idx_articles_listing(status, published_at, id); idx_articles_cover(cover_media_id); idx_articles_author(author_id)
FK fk_articles_cover: cover_media_id → media(id) ON DELETE SET NULL
FK fk_articles_author: author_id → team_members(id) ON DELETE SET NULL

## article_slug_history  _(migration 012_articles.sql)_
columns: id, article_id, old_slug, created_at
PK: id
unique: uq_article_old_slug(old_slug)
indexes: idx_ash_article(article_id)
FK fk_ash_article: article_id → articles(id) ON DELETE CASCADE
