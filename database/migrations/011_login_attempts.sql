-- Login throttling that does not depend on the session. Only hashes are stored, never raw IPs or emails.
CREATE TABLE IF NOT EXISTS login_attempts (
  id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ip_hash    CHAR(64) NOT NULL,
  email_hash CHAR(64) NULL,
  succeeded  TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_la_ip (ip_hash, succeeded, created_at),
  KEY idx_la_email (email_hash, succeeded, created_at),
  KEY idx_la_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Per-IP contact limit uses the existing ip_hash column.
ALTER TABLE contact_submissions ADD KEY idx_cs_ip (ip_hash, created_at)
