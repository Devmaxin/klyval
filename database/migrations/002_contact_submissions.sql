CREATE TABLE IF NOT EXISTS contact_submissions (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name         VARCHAR(120) NOT NULL,
  email        VARCHAR(190) NOT NULL,
  company      VARCHAR(160) NULL,
  project_type VARCHAR(40)  NOT NULL,
  budget       VARCHAR(80)  NULL,
  message      TEXT NOT NULL,
  status       ENUM('new','reviewing','contacted','in_progress','closed') NOT NULL DEFAULT 'new',
  ip_hash      CHAR(64) NULL,
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_status_created (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
