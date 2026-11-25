-- Staff-managed programs, recurring schedule notes, coach assignments, and events.
-- Schedule notes remain plain text until the academy confirms its scheduling needs.

CREATE TABLE programs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(120) NOT NULL,
    category VARCHAR(80) NOT NULL,
    age_group VARCHAR(120) NULL,
    description VARCHAR(4000) NOT NULL,
    fee_details VARCHAR(255) NULL,
    capacity INT UNSIGNED NULL,
    display_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    status ENUM('draft', 'published', 'archived') NOT NULL DEFAULT 'draft',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_programs_publication (status, display_order, title)
) ENGINE=InnoDB
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

CREATE TABLE program_schedules (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    program_id BIGINT UNSIGNED NOT NULL,
    schedule_text VARCHAR(255) NOT NULL,
    display_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_program_schedules_program (program_id, display_order),
    CONSTRAINT fk_program_schedules_program
        FOREIGN KEY (program_id) REFERENCES programs (id)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT
) ENGINE=InnoDB
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

CREATE TABLE program_coaches (
    program_id BIGINT UNSIGNED NOT NULL,
    coach_id BIGINT UNSIGNED NOT NULL,
    assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (program_id, coach_id),
    KEY idx_program_coaches_coach (coach_id),
    CONSTRAINT fk_program_coaches_program
        FOREIGN KEY (program_id) REFERENCES programs (id)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT,
    CONSTRAINT fk_program_coaches_coach
        FOREIGN KEY (coach_id) REFERENCES coaches (id)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT
) ENGINE=InnoDB
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

CREATE TABLE events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(160) NOT NULL,
    description VARCHAR(4000) NOT NULL,
    starts_at DATETIME NOT NULL,
    ends_at DATETIME NOT NULL,
    location VARCHAR(255) NOT NULL,
    registration_status ENUM('open', 'closed', 'not_required') NOT NULL DEFAULT 'not_required',
    photo_gallery_item_id BIGINT UNSIGNED NULL,
    status ENUM('draft', 'published', 'cancelled', 'archived') NOT NULL DEFAULT 'draft',
    published_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_events_schedule (status, starts_at),
    KEY idx_events_public_history (published_at, starts_at),
    CONSTRAINT fk_events_gallery_photo
        FOREIGN KEY (photo_gallery_item_id) REFERENCES gallery_items (id)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT
) ENGINE=InnoDB
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
