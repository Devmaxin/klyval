-- Insights. Category is a small fixed list owned by code (the three pillars plus studio news). Plain-text content only.
CREATE TABLE IF NOT EXISTS articles (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug            VARCHAR(120) NOT NULL,
  title           VARCHAR(200) NOT NULL,
  excerpt         VARCHAR(300) NULL,
  content         MEDIUMTEXT NULL,
  cover_media_id  INT UNSIGNED NULL,
  author_id       INT UNSIGNED NULL,
  category        ENUM('studio','build','create','launch') NOT NULL DEFAULT 'studio',
  status          ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
  published_at    DATETIME NULL,
  seo_title       VARCHAR(160) NULL,
  seo_description VARCHAR(255) NULL,
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_articles_slug (slug),
  KEY idx_articles_listing (status, published_at, id),
  KEY idx_articles_cover (cover_media_id),
  KEY idx_articles_author (author_id),
  CONSTRAINT fk_articles_cover FOREIGN KEY (cover_media_id) REFERENCES media (id) ON DELETE SET NULL,
  CONSTRAINT fk_articles_author FOREIGN KEY (author_id) REFERENCES team_members (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS article_slug_history (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  article_id INT UNSIGNED NOT NULL,
  old_slug   VARCHAR(120) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_article_old_slug (old_slug),
  KEY idx_ash_article (article_id),
  CONSTRAINT fk_ash_article FOREIGN KEY (article_id) REFERENCES articles (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
