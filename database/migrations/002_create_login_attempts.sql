-- Keep rate-limit state in short-lived opaque buckets. HMAC hashes are made in
-- PHP, so this table does not retain raw staff email addresses or IP addresses.
CREATE TABLE login_attempts (
    bucket_type ENUM('email', 'network') NOT NULL,
    bucket_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    window_started_at DATETIME NOT NULL,
    failed_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    locked_until DATETIME NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (bucket_type, bucket_hash),
    KEY idx_login_attempts_locked_until (locked_until),
    KEY idx_login_attempts_updated_at (updated_at)
) ENGINE=InnoDB
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
