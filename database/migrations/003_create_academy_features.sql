-- Public academy content, online registrations, player records, and gallery metadata.
-- Uploaded media bytes are stored outside public/; this table keeps only generated keys.

CREATE TABLE academy_content (
    id TINYINT UNSIGNED NOT NULL,
    introduction TEXT NULL,
    about_text TEXT NULL,
    mission TEXT NULL,
    vision TEXT NULL,
    coaching_philosophy TEXT NULL,
    contact_email VARCHAR(254) NULL,
    contact_phone VARCHAR(40) NULL,
    contact_location VARCHAR(255) NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id)
) ENGINE=InnoDB
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

INSERT INTO academy_content (id) VALUES (1);

-- Store only the information needed to contact and review a new applicant.
-- Guardian details are required by application validation for minors.
CREATE TABLE registrations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    first_name VARCHAR(80) NOT NULL,
    last_name VARCHAR(80) NOT NULL,
    date_of_birth DATE NOT NULL,
    email VARCHAR(254) NULL,
    phone VARCHAR(40) NULL,
    program_interest VARCHAR(120) NOT NULL,
    previous_experience VARCHAR(2000) NULL,
    guardian_name VARCHAR(160) NULL,
    guardian_relationship VARCHAR(60) NULL,
    guardian_email VARCHAR(254) NULL,
    guardian_phone VARCHAR(40) NULL,
    status ENUM('pending', 'under_review', 'approved', 'rejected', 'needs_information', 'cancelled')
        NOT NULL DEFAULT 'pending',
    admin_notes VARCHAR(2000) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_registrations_status_created (status, created_at),
    KEY idx_registrations_name (last_name, first_name),
    KEY idx_registrations_email (email)
) ENGINE=InnoDB
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

-- Player/member profiles are created only when an admin approves a registration.
-- Deactivate records instead of physically deleting them.
CREATE TABLE members (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    source_registration_id BIGINT UNSIGNED NULL,
    first_name VARCHAR(80) NOT NULL,
    last_name VARCHAR(80) NOT NULL,
    date_of_birth DATE NOT NULL,
    email VARCHAR(254) NULL,
    phone VARCHAR(40) NULL,
    program_interest VARCHAR(120) NOT NULL,
    previous_experience VARCHAR(2000) NULL,
    guardian_name VARCHAR(160) NULL,
    guardian_relationship VARCHAR(60) NULL,
    guardian_email VARCHAR(254) NULL,
    guardian_phone VARCHAR(40) NULL,
    status ENUM('active', 'inactive', 'suspended') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_members_registration (source_registration_id),
    KEY idx_members_status_name (status, last_name, first_name),
    CONSTRAINT fk_members_registration
        FOREIGN KEY (source_registration_id) REFERENCES registrations (id)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT
) ENGINE=InnoDB
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

-- Generated storage keys prevent user filenames from becoming filesystem paths.
-- Draft media is private from the public gallery until an admin publishes it.
CREATE TABLE gallery_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    storage_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    media_kind ENUM('image', 'video') NOT NULL,
    mime_type ENUM('image/jpeg', 'image/png', 'image/webp', 'video/mp4', 'video/webm') NOT NULL,
    file_size INT UNSIGNED NOT NULL,
    title VARCHAR(120) NOT NULL,
    alternative_text VARCHAR(255) NOT NULL,
    caption VARCHAR(1000) NULL,
    status ENUM('draft', 'published', 'archived') NOT NULL DEFAULT 'draft',
    uploaded_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_gallery_storage_key (storage_key),
    KEY idx_gallery_publication (status, created_at),
    CONSTRAINT fk_gallery_uploaded_by
        FOREIGN KEY (uploaded_by_user_id) REFERENCES users (id)
        ON UPDATE RESTRICT
        ON DELETE SET NULL
) ENGINE=InnoDB
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

-- Store only an HMAC of the submitting network address and expire old buckets.
CREATE TABLE registration_rate_limits (
    network_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    window_started_at DATETIME NOT NULL,
    submission_count INT UNSIGNED NOT NULL DEFAULT 0,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (network_hash),
    KEY idx_registration_limits_updated (updated_at)
) ENGINE=InnoDB
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
