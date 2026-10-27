-- Gap found while building the admin: the brief gives projects a Services field but no relationship existed.
-- Additive. Links a project to the services it delivered. Pillars shown on a project are derived from these.
CREATE TABLE IF NOT EXISTS project_services (
  project_id INT UNSIGNED NOT NULL,
  service_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (project_id, service_id),
  KEY idx_pserv_service (service_id, project_id),
  CONSTRAINT fk_pserv_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE,
  CONSTRAINT fk_pserv_service FOREIGN KEY (service_id) REFERENCES services (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
