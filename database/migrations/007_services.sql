-- Services. pillar (build, create, launch) is fixed by code. is_active replaces deletion for day-to-day use.
CREATE TABLE IF NOT EXISTS services (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug            VARCHAR(120) NOT NULL,
  name            VARCHAR(120) NOT NULL,
  pillar          ENUM('build','create','launch') NOT NULL,
  description     TEXT NULL,
  visual_media_id INT UNSIGNED NULL,
  show_on_home    TINYINT(1) NOT NULL DEFAULT 0,
  display_order   INT UNSIGNED NOT NULL DEFAULT 0,
  is_active       TINYINT(1) NOT NULL DEFAULT 1,
  is_placeholder  TINYINT(1) NOT NULL DEFAULT 0,
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_services_slug (slug),
  KEY idx_services_listing (pillar, is_active, display_order, id),
  KEY idx_services_home (show_on_home, is_active, display_order, id),
  KEY idx_services_visual (visual_media_id),
  CONSTRAINT fk_services_visual FOREIGN KEY (visual_media_id) REFERENCES media (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS service_features (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  service_id    INT UNSIGNED NOT NULL,
  label         VARCHAR(200) NOT NULL,
  display_order INT UNSIGNED NOT NULL DEFAULT 0,
  KEY idx_sf_service (service_id, display_order, id),
  CONSTRAINT fk_sf_service FOREIGN KEY (service_id) REFERENCES services (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
