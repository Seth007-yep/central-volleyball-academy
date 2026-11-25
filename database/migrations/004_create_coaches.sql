-- Coach profiles are editable by staff and published only after academy review.
-- Public coach photos reference a permission-approved image in the gallery.

CREATE TABLE coaches (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    first_name VARCHAR(80) NOT NULL,
    last_name VARCHAR(80) NOT NULL,
    role_title VARCHAR(120) NOT NULL,
    biography VARCHAR(4000) NOT NULL,
    qualifications VARCHAR(2000) NULL,
    photo_gallery_item_id BIGINT UNSIGNED NULL,
    display_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    status ENUM('draft', 'published', 'archived') NOT NULL DEFAULT 'draft',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_coaches_publication (status, display_order, last_name),
    CONSTRAINT fk_coaches_gallery_photo
        FOREIGN KEY (photo_gallery_item_id) REFERENCES gallery_items (id)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT
) ENGINE=InnoDB
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
