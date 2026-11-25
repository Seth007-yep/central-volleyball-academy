-- Add the limited academy-staff role and the missing coach experience field.
-- Existing records remain unchanged and existing administrators keep their role.

INSERT IGNORE INTO roles (slug, display_name)
VALUES ('staff', 'Academy Staff');

ALTER TABLE coaches
    ADD COLUMN experience VARCHAR(2000) NULL AFTER biography;
