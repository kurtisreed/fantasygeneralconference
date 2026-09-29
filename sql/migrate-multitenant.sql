-- Multi-tenant migration for an EXISTING database (run once in phpMyAdmin).
-- Everything already there becomes the "default" group, which keeps being
-- served at the bare domain. Existing admins stay super-admins (org_id NULL).
-- Fresh installs don't need this; sql/schema.sql already has it.

CREATE TABLE IF NOT EXISTS orgs (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  slug          VARCHAR(40)  NOT NULL,
  name          VARCHAR(120) NOT NULL,
  leader_name   VARCHAR(80)  NOT NULL DEFAULT '',
  contact_line  VARCHAR(160) NOT NULL DEFAULT '',
  accent_color  CHAR(7)      NULL,
  logo_path     VARCHAR(120) NULL,
  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_org_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO orgs (slug, name, leader_name, contact_line)
VALUES ('default', 'Fantasy General Conference', 'Bishop Reed', 'Bishop Reed or Porter')
ON DUPLICATE KEY UPDATE name = name;

ALTER TABLE events ADD COLUMN org_id INT NULL AFTER id;
UPDATE events SET org_id = (SELECT id FROM orgs WHERE slug = 'default');
ALTER TABLE events
  MODIFY org_id INT NOT NULL,
  DROP INDEX uq_event_slug,
  ADD UNIQUE KEY uq_event_slug (org_id, slug),
  ADD CONSTRAINT fk_event_org FOREIGN KEY (org_id) REFERENCES orgs(id) ON DELETE CASCADE;

ALTER TABLE admins
  ADD COLUMN org_id INT NULL AFTER remember_token_hash,
  ADD CONSTRAINT fk_admin_org FOREIGN KEY (org_id) REFERENCES orgs(id) ON DELETE CASCADE;
