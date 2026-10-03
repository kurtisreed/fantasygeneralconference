-- Run once in phpMyAdmin BEFORE pulling the announcement feature.
ALTER TABLE orgs ADD COLUMN announcement VARCHAR(500) NULL AFTER contact_line;
