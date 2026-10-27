-- Media library. Images only. Files live under public/uploads with random server-generated names.
CREATE TABLE IF NOT EXISTS media (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  filename      VARCHAR(80)  NOT NULL,
  original_name VARCHAR(255) NULL,
  mime          VARCHAR(40)  NOT NULL,
  width         SMALLINT UNSIGNED NOT NULL,
  height        SMALLINT UNSIGNED NOT NULL,
  bytes         INT UNSIGNED NOT NULL,
  alt_text      VARCHAR(250) NULL,
  uploaded_by   INT UNSIGNED NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_media_filename (filename),
  KEY idx_media_created (created_at, id),
  KEY idx_media_uploader (uploaded_by),
  CONSTRAINT fk_media_uploader FOREIGN KEY (uploaded_by) REFERENCES admin_users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
