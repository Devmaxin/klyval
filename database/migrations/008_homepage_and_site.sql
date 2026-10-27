-- Homepage copy and section flags. Allowed keys are whitelisted in code, never created from user input.
CREATE TABLE IF NOT EXISTS homepage_settings (
  setting_key   VARCHAR(60) NOT NULL PRIMARY KEY,
  setting_value TEXT NULL,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Real numbers only. No rows are ever seeded and the section stays hidden while this is empty.
CREATE TABLE IF NOT EXISTS homepage_stats (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  label         VARCHAR(80) NOT NULL,
  value         VARCHAR(40) NOT NULL,
  display_order INT UNSIGNED NOT NULL DEFAULT 0,
  is_active     TINYINT(1) NOT NULL DEFAULT 1,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_stats_listing (is_active, display_order, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS social_links (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  platform      ENUM('x','instagram','linkedin','github','behance','dribbble','youtube','tiktok') NOT NULL,
  url           VARCHAR(500) NOT NULL,
  display_order INT UNSIGNED NOT NULL DEFAULT 0,
  is_active     TINYINT(1) NOT NULL DEFAULT 1,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_social_platform (platform),
  KEY idx_social_listing (is_active, display_order, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Site-level images with a real foreign key (logo, social share image, favicon).
CREATE TABLE IF NOT EXISTS site_media (
  slot       ENUM('logo','og_image','favicon') NOT NULL PRIMARY KEY,
  media_id   INT UNSIGNED NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_site_media (media_id),
  CONSTRAINT fk_site_media FOREIGN KEY (media_id) REFERENCES media (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
