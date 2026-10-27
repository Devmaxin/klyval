-- Team. is_active replaces deletion for day-to-day use.
CREATE TABLE IF NOT EXISTS team_members (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug              VARCHAR(120) NOT NULL,
  name              VARCHAR(120) NOT NULL,
  role              VARCHAR(120) NOT NULL,
  portrait_media_id INT UNSIGNED NULL,
  short_bio         VARCHAR(280) NULL,
  full_bio          MEDIUMTEXT NULL,
  portfolio_url     VARCHAR(500) NULL,
  featured          TINYINT(1) NOT NULL DEFAULT 0,
  display_order     INT UNSIGNED NOT NULL DEFAULT 0,
  is_active         TINYINT(1) NOT NULL DEFAULT 1,
  is_placeholder    TINYINT(1) NOT NULL DEFAULT 0,
  created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_team_slug (slug),
  KEY idx_team_listing (is_active, display_order, id),
  KEY idx_team_featured (featured, is_active, display_order, id),
  KEY idx_team_portrait (portrait_media_id),
  CONSTRAINT fk_team_portrait FOREIGN KEY (portrait_media_id) REFERENCES media (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS team_slug_history (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  member_id  INT UNSIGNED NOT NULL,
  old_slug   VARCHAR(120) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_team_old_slug (old_slug),
  KEY idx_tsh_member (member_id),
  CONSTRAINT fk_tsh_member FOREIGN KEY (member_id) REFERENCES team_members (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Platforms are a fixed list owned by code. One link per platform per person.
CREATE TABLE IF NOT EXISTS team_socials (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  member_id     INT UNSIGNED NOT NULL,
  platform      ENUM('x','instagram','linkedin','github','behance','dribbble','youtube','tiktok','website') NOT NULL,
  url           VARCHAR(500) NOT NULL,
  display_order INT UNSIGNED NOT NULL DEFAULT 0,
  UNIQUE KEY uq_team_social (member_id, platform),
  CONSTRAINT fk_team_social_member FOREIGN KEY (member_id) REFERENCES team_members (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS team_skills (
  member_id INT UNSIGNED NOT NULL,
  skill_id  INT UNSIGNED NOT NULL,
  PRIMARY KEY (member_id, skill_id),
  KEY idx_tskill_skill (skill_id),
  CONSTRAINT fk_tskill_member FOREIGN KEY (member_id) REFERENCES team_members (id) ON DELETE CASCADE,
  CONSTRAINT fk_tskill_skill FOREIGN KEY (skill_id) REFERENCES skills (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The single project/team relationship. Indexed both ways: a project lists its team, a profile lists its projects.
CREATE TABLE IF NOT EXISTS project_team (
  project_id    INT UNSIGNED NOT NULL,
  member_id     INT UNSIGNED NOT NULL,
  role_label    VARCHAR(120) NULL,
  display_order INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (project_id, member_id),
  KEY idx_pteam_member (member_id, project_id),
  CONSTRAINT fk_pteam_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE,
  CONSTRAINT fk_pteam_member FOREIGN KEY (member_id) REFERENCES team_members (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
