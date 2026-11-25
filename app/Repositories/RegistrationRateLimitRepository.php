<?php

declare(strict_types=1);

namespace Cva\Repositories;

use PDO;

/** Apply a short public-registration limit without retaining raw IP addresses. */
final class RegistrationRateLimitRepository
{
    private const MAX_SUBMISSIONS_PER_HOUR = 10;

    public function __construct(private PDO $database)
    {
    }

    public function allowNext(string $networkHash): bool
    {
        // Keep pseudonymous buckets only briefly; this is not a long-term access log.
        $this->database->exec(
            'DELETE FROM registration_rate_limits
             WHERE updated_at < CURRENT_TIMESTAMP - INTERVAL 1 DAY'
        );

        $statement = $this->database->prepare(
            'INSERT INTO registration_rate_limits (
                network_hash, window_started_at, submission_count
             ) VALUES (:network_hash, CURRENT_TIMESTAMP, 1)
             ON DUPLICATE KEY UPDATE
                submission_count = IF(
                    window_started_at <= CURRENT_TIMESTAMP - INTERVAL 1 HOUR,
                    1,
                    LEAST(submission_count + 1, 11)
                ),
                window_started_at = IF(
                    window_started_at <= CURRENT_TIMESTAMP - INTERVAL 1 HOUR,
                    CURRENT_TIMESTAMP,
                    window_started_at
                )'
        );
        $statement->execute(['network_hash' => $networkHash]);

        $lookup = $this->database->prepare(
            'SELECT submission_count FROM registration_rate_limits WHERE network_hash = :network_hash'
        );
        $lookup->execute(['network_hash' => $networkHash]);

        return (int) $lookup->fetchColumn() <= self::MAX_SUBMISSIONS_PER_HOUR;
    }
}
