-- Projects. kind is a fixed Klyval taxonomy owned by code. Listing order is always display_order then id.
CREATE TABLE IF NOT EXISTS projects (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug           VARCHAR(120) NOT NULL,
  name           VARCHAR(160) NOT NULL,
  client_name    VARCHAR(160) NULL,
  kind           ENUM('client','product','experiment') NOT NULL DEFAULT 'client',
  description    TEXT NULL,
  case_study     MEDIUMTEXT NULL,
  cover_media_id INT UNSIGNED NULL,
  video_url      VARCHAR(500) NULL,
  project_url    VARCHAR(500) NULL,
  status         ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
  featured       TINYINT(1) NOT NULL DEFAULT 0,
  display_order  INT UNSIGNED NOT NULL DEFAULT 0,
  seo_title      VARCHAR(160) NULL,
  seo_description VARCHAR(255) NULL,
  is_placeholder TINYINT(1) NOT NULL DEFAULT 0,
  published_at   DATETIME NULL,
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_projects_slug (slug),
  KEY idx_projects_listing (status, display_order, id),
  KEY idx_projects_featured (featured, status, display_order, id),
  KEY idx_projects_kind (kind, status, display_order, id),
  KEY idx_projects_cover (cover_media_id),
  CONSTRAINT fk_projects_cover FOREIGN KEY (cover_media_id) REFERENCES media (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Previous slugs, kept so old URLs can redirect (301) instead of breaking.
CREATE TABLE IF NOT EXISTS project_slug_history (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id INT UNSIGNED NOT NULL,
  old_slug   VARCHAR(120) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_project_old_slug (old_slug),
  KEY idx_psh_project (project_id),
  CONSTRAINT fk_psh_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Gallery.
CREATE TABLE IF NOT EXISTS project_media (
  project_id    INT UNSIGNED NOT NULL,
  media_id      INT UNSIGNED NOT NULL,
  display_order INT UNSIGNED NOT NULL DEFAULT 0,
  caption       VARCHAR(250) NULL,
  PRIMARY KEY (project_id, media_id),
  KEY idx_pm_order (project_id, display_order, media_id),
  KEY idx_pm_media (media_id),
  CONSTRAINT fk_pm_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE,
  CONSTRAINT fk_pm_media FOREIGN KEY (media_id) REFERENCES media (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS project_technologies (
  project_id    INT UNSIGNED NOT NULL,
  technology_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (project_id, technology_id),
  KEY idx_ptech_technology (technology_id),
  CONSTRAINT fk_ptech_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE,
  CONSTRAINT fk_ptech_technology FOREIGN KEY (technology_id) REFERENCES technologies (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
