<?php

declare(strict_types=1);

namespace Cva\Repositories;

use PDO;

/** Store short-lived, opaque login-rate-limit buckets (never raw email or IP). */
final class LoginAttemptRepository
{
    public function __construct(private PDO $database)
    {
    }

    /** @param array{email:string,network:string} $buckets */
    public function isLocked(array $buckets): bool
    {
        $statement = $this->database->prepare(
            'SELECT 1 FROM login_attempts
             WHERE bucket_type = :bucket_type
               AND bucket_hash = :bucket_hash
               AND locked_until > UTC_TIMESTAMP()
             LIMIT 1'
        );

        foreach ($buckets as $type => $hash) {
            $statement->execute([
                'bucket_type' => $type,
                'bucket_hash' => $hash,
            ]);

            if ($statement->fetchColumn() !== false) {
                return true;
            }
        }

        return false;
    }

    /** Count failures in a 15-minute window and cool down after the fifth. */
    public function recordFailure(array $buckets): void
    {
        $statement = $this->database->prepare(
            'INSERT INTO login_attempts
                (bucket_type, bucket_hash, window_started_at, failed_attempts, locked_until)
             VALUES
                (:bucket_type, :bucket_hash, UTC_TIMESTAMP(), 1, NULL)
             ON DUPLICATE KEY UPDATE
                locked_until = CASE
                    WHEN window_started_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 15 MINUTE)
                        THEN NULL
                    WHEN failed_attempts >= 4
                        THEN DATE_ADD(UTC_TIMESTAMP(), INTERVAL 15 MINUTE)
                    ELSE locked_until
                END,
                failed_attempts = CASE
                    WHEN window_started_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 15 MINUTE)
                        THEN 1
                    ELSE LEAST(failed_attempts + 1, 255)
                END,
                window_started_at = CASE
                    WHEN window_started_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 15 MINUTE)
                        THEN UTC_TIMESTAMP()
                    ELSE window_started_at
                END'
        );

        foreach ($buckets as $type => $hash) {
            $statement->execute([
                'bucket_type' => $type,
                'bucket_hash' => $hash,
            ]);
        }
    }

    /** Remove old buckets regularly so the table does not grow without bound. */
    public function pruneExpired(): void
    {
        $this->database->exec(
            'DELETE FROM login_attempts
             WHERE updated_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY)'
        );
    }

    /** Clear both rate-limit buckets after a valid sign-in. */
    public function clear(array $buckets): void
    {
        $statement = $this->database->prepare(
            'DELETE FROM login_attempts
             WHERE bucket_type = :bucket_type AND bucket_hash = :bucket_hash'
        );

        foreach ($buckets as $type => $hash) {
            $statement->execute([
                'bucket_type' => $type,
                'bucket_hash' => $hash,
            ]);
        }
    }
}
