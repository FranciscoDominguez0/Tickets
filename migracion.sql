
ALTER TABLE organizations DROP COLUMN phone_ext;

ALTER TABLE staff ADD COLUMN dark_mode TINYINT(1) DEFAULT 0 AFTER signature;

