-- Synthetic fixture for scripts/migrate-data/import-dump.php — NOT real data.
-- Shaped like a phpMyAdmin / mysqldump export (column lists included, MySQL escapes).
-- Exercises: literal quotes, doubled '', backslashes, \n and \r\n, NULL, emoji (utf8mb4),
-- 0/1 tinyint flags, datetime strings, FK ordering, a slug-history orphan row.

INSERT INTO `admin_users` (`id`, `name`, `email`, `password_hash`, `role`, `is_active`, `last_login_at`, `created_at`, `updated_at`) VALUES
(7, 'Owner', 'owner@klyval.local', '$2y$10$placeholderhashplaceholderhashplacehold', 'admin', 1, NULL, '2025-03-14 08:00:00', '2025-11-02 10:15:00'),
(8, 'Edïtor', 'editor@klyval.local', '$2y$10$placeholderhashplaceholderhashplacehold', 'editor', 1, '2025-11-01 09:00:00', '2025-03-20 08:00:00', '2025-09-21 09:00:00');

INSERT INTO `media` (`id`, `filename`, `original_name`, `mime`, `width`, `height`, `bytes`, `alt_text`, `uploaded_by`, `created_at`, `updated_at`) VALUES
(11, 'cover-hero.png', 'IMG_0923.png', 'image/png', 1920, 1080, 302400, 'Hero cover — who\'s behind it', 7, '2025-04-01 12:00:00', '2025-04-01 12:00:00'),
(12, 'note-with-backslash.png', 'note (5).png', 'image/png', 800, 600, 80123, NULL, 8, '2025-04-02 13:30:00', '2025-04-02 13:30:00');

INSERT INTO `technologies` (`id`, `name`, `slug`, `created_at`) VALUES
(21, 'PHP', 'php', '2025-04-01 12:00:00'),
(22, 'Four Oh Four ///cdf/é', 'craft-emoji', '2025-04-01 12:00:00');

INSERT INTO `skills` (`id`, `name`, `slug`, `created_at`) VALUES
(31, 'Design', 'design', '2025-04-01 12:00:00');

INSERT INTO `services` (`id`, `slug`, `name`, `pillar`, `description`, `visual_media_id`, `show_on_home`, `display_order`, `is_active`, `is_placeholder`, `created_at`, `updated_at`) VALUES
(41, 'web-apps', 'Web apps', 'build', 'Design and delivery of web products.', NULL, 1, 1, 1, 0, '2025-04-01 12:00:00', '2025-06-11 09:00:00');

INSERT INTO `service_features` (`id`, `service_id`, `label`, `display_order`) VALUES
(42, 41, 'Discovery sprint', 1),
(43, 41, 'Prototypes & handoff', 2);

INSERT INTO `team_members` (`id`, `slug`, `name`, `role`, `portrait_media_id`, `short_bio`, `full_bio`, `portfolio_url`, `featured`, `display_order`, `is_active`, `is_placeholder`, `created_at`, `updated_at`) VALUES
(51, 'kly-avery', 'Kly Avery', 'Founder & Principal', 12, 'Sets the direction.', 'Line one\r\nLine two with ''quotes'' and a backslash \\.', 'https://avery.example.com', 1, 1, 1, 0, '2025-03-14 08:00:00', '2025-07-08 10:00:00');

INSERT INTO `team_socials` (`id`, `member_id`, `platform`, `url`, `display_order`) VALUES
(52, 51, 'linkedin', 'https://www.linkedin.com/in/klyavery', 1);

INSERT INTO `projects` (`id`, `slug`, `name`, `client_name`, `kind`, `description`, `case_study`, `cover_media_id`, `video_url`, `project_url`, `status`, `featured`, `display_order`, `seo_title`, `seo_description`, `is_placeholder`, `published_at`, `created_at`, `updated_at`) VALUES
(61, 'sunrise-rebrand', 'Sunrise Coffee Rebrand', 'Sunrise Coffee', 'client', 'A full rebrand delivered in six weeks.\n\n“Smäll 🎉 out” — copy with double¬backslash: \\.', 'Phase 1: workshops.\r\nPhase 2: identity.', 11, NULL, 'https://sunrise.example.com', 'published', 1, 1, 'Sunrise — Klyval', 'A rebrand case study', 0, '2025-06-01 10:00:00', '2025-05-01 09:00:00', '2025-06-01 10:00:00'),
(62, 'internal-tool-exp', 'Internal Ops Tool', NULL, 'experiment', 'A small internal experiment.', NULL, NULL, NULL, NULL, 'draft', 0, 2, NULL, NULL, 0, NULL, '2025-06-10 09:00:00', '2025-06-10 09:00:00');

INSERT INTO `project_slug_history` (`id`, `project_id`, `old_slug`, `created_at`) VALUES
(71, 61, 'sunrise-old-name', '2025-06-01 10:00:00');

INSERT INTO `project_technologies` (`project_id`, `technology_id`) VALUES
(61, 21),
(61, 22);

INSERT INTO `project_media` (`project_id`, `media_id`, `display_order`, `caption`) VALUES
(61, 12, 1, 'Studio shot');

INSERT INTO `articles` (`id`, `slug`, `title`, `excerpt`, `content`, `cover_media_id`, `author_id`, `category`, `status`, `published_at`, `seo_title`, `seo_description`, `created_at`, `updated_at`) VALUES
(81, 'studio-notes', 'Studio Notes', 'What we changed this quarter.', 'It started with <em>a small team</em> and a demo on a Friday.', NULL, 51, 'studio', 'published', '2025-10-01 08:00:00', NULL, NULL, '2025-09-28 08:00:00', '2025-10-01 08:00:00');

INSERT INTO `article_slug_history` (`id`, `article_id`, `old_slug`, `created_at`) VALUES
(82, 81, 'notes-old', '2025-10-01 08:00:00');

INSERT INTO `site_settings` (`setting_key`, `setting_value`, `updated_at`) VALUES
('footer_text', 'Built by Klyval — 2025', '2025-11-02 10:15:00'),
('maintenance_mode', '0', '2025-11-02 10:15:00');

INSERT INTO `site_media` (`slot`, `media_id`, `updated_at`) VALUES
('logo', 11, '2025-11-02 10:15:00');

INSERT INTO `contact_submissions` (`id`, `name`, `email`, `company`, `project_type`, `budget`, `message`, `status`, `ip_hash`, `created_at`, `updated_at`) VALUES
(101, 'İnsan Name', 'info@sunrise.example.com', 'Sunrise', 'Website', '10–20k', 'We need a launch site before October.', 'new', 'sha0fdeadbeef', '2025-10-21 15:42:00', '2025-10-21 15:42:00');

INSERT INTO `homepage_stats` (`id`, `label`, `value`, `display_order`, `is_active`, `created_at`, `updated_at`) VALUES
(111, 'Projects shipped', '48', 1, 1, '2025-04-01 12:00:00', '2025-04-01 12:00:00');

INSERT INTO `social_links` (`id`, `platform`, `url`, `display_order`, `is_active`, `created_at`, `updated_at`) VALUES
(112, 'instagram', 'https://instagram.com/klyval', 1, 1, '2025-04-01 12:00:00', '2025-04-01 12:00:00');

INSERT INTO `homepage_settings` (`setting_key`, `setting_value`, `updated_at`) VALUES
('home_headline', 'We build things that ship.', '2025-04-01 12:00:00');

INSERT INTO `team_skills` (`member_id`, `skill_id`) VALUES
(51, 31);

INSERT INTO `project_team` (`project_id`, `member_id`, `role_label`, `display_order`) VALUES
(61, 51, 'Creative lead', 1);

INSERT INTO `project_services` (`project_id`, `service_id`) VALUES
(61, 41);
