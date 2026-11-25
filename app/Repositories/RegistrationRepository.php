<?php

declare(strict_types=1);

namespace Cva\Repositories;

use PDO;
use RuntimeException;

/** Store public applications and provide the protected review workflow. */
final class RegistrationRepository
{
    public function __construct(private PDO $database)
    {
    }

    public function create(array $application): int
    {
        $statement = $this->database->prepare(
            'INSERT INTO registrations (
                first_name, last_name, date_of_birth, email, phone,
                program_interest, previous_experience, guardian_name,
                guardian_relationship, guardian_email, guardian_phone
             ) VALUES (
                :first_name, :last_name, :date_of_birth, :email, :phone,
                :program_interest, :previous_experience, :guardian_name,
                :guardian_relationship, :guardian_email, :guardian_phone
             )'
        );
        $statement->execute($application);

        return (int) $this->database->lastInsertId();
    }

    /** Serialize duplicate checks for one applicant so simultaneous requests cannot race. */
    public function acquireDuplicateCheckLock(array $application, string $secret): ?string
    {
        $normalizeName = static function (string $value): string {
            return function_exists('mb_strtolower')
                ? mb_strtolower(trim($value), 'UTF-8')
                : strtolower(trim($value));
        };
        $identity = implode("\0", [
            $normalizeName((string) ($application['first_name'] ?? '')),
            $normalizeName((string) ($application['last_name'] ?? '')),
            (string) ($application['date_of_birth'] ?? ''),
        ]);
        // A keyed digest keeps the MySQL advisory-lock name opaque and under its 64-character limit.
        $lockName = hash_hmac('sha256', 'registration-duplicate:' . $identity, $secret);
        $statement = $this->database->prepare('SELECT GET_LOCK(:lock_name, 5)');
        $statement->execute(['lock_name' => $lockName]);

        return (int) $statement->fetchColumn() === 1 ? $lockName : null;
    }

    public function releaseDuplicateCheckLock(string $lockName): void
    {
        $statement = $this->database->prepare('SELECT RELEASE_LOCK(:lock_name)');
        $statement->execute(['lock_name' => $lockName]);
        $statement->fetchColumn();
    }

    /** Match name and birth date plus one shared applicant/guardian contact. */
    public function hasDuplicateApplicant(array $application): bool
    {
        $emails = [];
        foreach (['email', 'guardian_email'] as $field) {
            $value = $application[$field] ?? null;
            if (is_string($value) && trim($value) !== '') {
                $emails[] = strtolower(trim($value));
            }
        }
        $emails = array_values(array_unique($emails));

        $phones = [];
        foreach (['phone', 'guardian_phone'] as $field) {
            $value = $application[$field] ?? null;
            if (is_string($value) && trim($value) !== '') {
                $phones[] = preg_replace('/[^0-9]/', '', $value) ?? '';
            }
        }
        $phones = array_values(array_unique(array_filter($phones, static fn (string $phone): bool => $phone !== '')));
        if ($emails === [] && $phones === []) {
            return false;
        }

        $parameters = [
            'first_name' => (string) ($application['first_name'] ?? ''),
            'last_name' => (string) ($application['last_name'] ?? ''),
            'date_of_birth' => (string) ($application['date_of_birth'] ?? ''),
        ];
        $contactClauses = [];
        foreach ($emails as $index => $email) {
            foreach (['email', 'guardian_email'] as $columnIndex => $column) {
                $name = ':duplicate_email_' . $index . '_' . $columnIndex;
                $contactClauses[] = "LOWER(TRIM(COALESCE(existing.{$column}, ''))) = {$name}";
                $parameters[$name] = $email;
            }
        }
        foreach ($phones as $index => $phone) {
            foreach (['phone', 'guardian_phone'] as $columnIndex => $column) {
                $name = ':duplicate_phone_' . $index . '_' . $columnIndex;
                $contactClauses[] = $this->normalizedPhoneSql('existing.' . $column) . " = {$name}";
                $parameters[$name] = $phone;
            }
        }

        // Rejected/cancelled applications can be resubmitted; existing member rows always block repeats.
        $statement = $this->database->prepare(
            "SELECT 1 FROM (
                SELECT first_name, last_name, date_of_birth, email, phone, guardian_email, guardian_phone
                FROM registrations
                WHERE status IN ('pending', 'under_review', 'needs_information', 'approved')
                UNION ALL
                SELECT first_name, last_name, date_of_birth, email, phone, guardian_email, guardian_phone
                FROM members
             ) AS existing
             WHERE existing.first_name = :first_name
               AND existing.last_name = :last_name
               AND existing.date_of_birth = :date_of_birth
               AND (" . implode(' OR ', $contactClauses) . ")
             LIMIT 1"
        );
        $statement->execute($parameters);

        return $statement->fetchColumn() !== false;
    }

    public function countForAdminList(
        ?string $status,
        string $search,
        string $phone = '',
        string $program = '',
        ?string $dateFrom = null,
        ?string $dateTo = null
    ): int
    {
        [$where, $parameters] = $this->filters($status, $search, $phone, $program, $dateFrom, $dateTo);
        $statement = $this->database->prepare(
            'SELECT COUNT(*) FROM registrations ' . $where
        );
        $statement->execute($parameters);

        return (int) $statement->fetchColumn();
    }

    public function pageForAdminList(
        ?string $status,
        string $search,
        int $limit,
        int $offset,
        string $phone = '',
        string $program = '',
        ?string $dateFrom = null,
        ?string $dateTo = null
    ): array
    {
        [$where, $parameters] = $this->filters($status, $search, $phone, $program, $dateFrom, $dateTo);
        $statement = $this->database->prepare(
            'SELECT id, first_name, last_name, date_of_birth, email, phone,
                    program_interest, status, created_at
             FROM registrations ' . $where . '
             ORDER BY created_at DESC, id DESC
             LIMIT :row_limit OFFSET :row_offset'
        );
        foreach ($parameters as $name => $value) {
            $statement->bindValue($name, $value, PDO::PARAM_STR);
        }
        $statement->bindValue(':row_limit', max(1, min(100, $limit)), PDO::PARAM_INT);
        $statement->bindValue(':row_offset', max(0, $offset), PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    public function findForAdmin(int $id): ?array
    {
        $statement = $this->database->prepare(
            'SELECT id, first_name, last_name, date_of_birth, email, phone,
                    program_interest, previous_experience, guardian_name,
                    guardian_relationship, guardian_email, guardian_phone,
                    status, admin_notes, created_at, updated_at
             FROM registrations WHERE id = :id'
        );
        $statement->execute(['id' => $id]);
        $registration = $statement->fetch();

        return is_array($registration) ? $registration : null;
    }

    /** Change review status; approval and player creation happen atomically. */
    public function review(
        int $id,
        string $status,
        ?string $adminNotes,
        ?int $actorId,
        ?string $expectedCurrentStatus = null
    ): void
    {
        // Allow the surrounding smoke checks or a future larger workflow to own a transaction.
        $ownsTransaction = !$this->database->inTransaction();
        if ($ownsTransaction) {
            $this->database->beginTransaction();
        }
        try {
            $lookup = $this->database->prepare(
                'SELECT id, first_name, last_name, date_of_birth, email, phone, status,
                        program_interest, previous_experience, guardian_name,
                        guardian_relationship, guardian_email, guardian_phone
                 FROM registrations WHERE id = :id FOR UPDATE'
            );
            $lookup->execute(['id' => $id]);
            $application = $lookup->fetch();
            if (!is_array($application)) {
                throw new RuntimeException('The registration could not be found.');
            }
            if ($expectedCurrentStatus !== null && $application['status'] !== $expectedCurrentStatus) {
                throw new RuntimeException('The registration status changed before confirmation.');
            }

            if ($status === 'approved') {
                // A unique source ID makes retries safe after a double submit.
                $insert = $this->database->prepare(
                    'INSERT INTO members (
                        source_registration_id, first_name, last_name, date_of_birth,
                        email, phone, program_interest, previous_experience,
                        guardian_name, guardian_relationship, guardian_email, guardian_phone
                     ) VALUES (
                        :id, :first_name, :last_name, :date_of_birth,
                        :email, :phone, :program_interest, :previous_experience,
                        :guardian_name, :guardian_relationship, :guardian_email, :guardian_phone
                     ) ON DUPLICATE KEY UPDATE source_registration_id = VALUES(source_registration_id)'
                );
                // The locked status is used for stale-confirmation detection, not member data.
                unset($application['status']);
                $insert->execute($application);
            }

            $update = $this->database->prepare(
                'UPDATE registrations SET status = :status, admin_notes = :admin_notes
                 WHERE id = :id'
            );
            $update->execute(['status' => $status, 'admin_notes' => $adminNotes, 'id' => $id]);
            (new AuditLogRepository($this->database))->record(
                $actorId,
                'registration.status.' . $status,
                (string) $id,
                'registration'
            );
            if ($ownsTransaction) {
                $this->database->commit();
            }
        } catch (\Throwable $error) {
            if ($ownsTransaction && $this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $error;
        }
    }

    private function filters(
        ?string $status,
        string $search,
        string $phone,
        string $program,
        ?string $dateFrom,
        ?string $dateTo
    ): array
    {
        $clauses = [];
        $parameters = [];
        if ($status !== null) {
            $clauses[] = 'status = :status';
            $parameters[':status'] = $status;
        }
        if ($search !== '') {
            $clauses[] = '(first_name LIKE :search_first OR last_name LIKE :search_last OR email LIKE :search_email)';
            $parameters[':search_first'] = '%' . $search . '%';
            $parameters[':search_last'] = '%' . $search . '%';
            $parameters[':search_email'] = '%' . $search . '%';
        }
        if ($phone !== '') {
            $clauses[] = '(' . $this->normalizedPhoneSql('phone') . ' LIKE :phone_applicant'
                . ' OR ' . $this->normalizedPhoneSql('guardian_phone') . ' LIKE :phone_guardian)';
            $parameters[':phone_applicant'] = '%' . $phone . '%';
            $parameters[':phone_guardian'] = '%' . $phone . '%';
        }
        if ($program !== '') {
            $clauses[] = 'program_interest LIKE :program_interest';
            $parameters[':program_interest'] = '%' . $program . '%';
        }
        if ($dateFrom !== null) {
            $clauses[] = 'created_at >= :received_from';
            $parameters[':received_from'] = $dateFrom;
        }
        if ($dateTo !== null) {
            // Exclusive next-day bound includes the whole end date and preserves index use.
            $clauses[] = 'created_at < DATE_ADD(:received_to, INTERVAL 1 DAY)';
            $parameters[':received_to'] = $dateTo;
        }

        return [$clauses === [] ? '' : 'WHERE ' . implode(' AND ', $clauses), $parameters];
    }

    /** Strip phone punctuation accepted by registration validation inside SQL. */
    private function normalizedPhoneSql(string $column): string
    {
        $expression = "COALESCE({$column}, '')";
        foreach (['+', '(', ')', '.', '-', ' '] as $punctuation) {
            $expression = "REPLACE({$expression}, '{$punctuation}', '')";
        }

        return "REPLACE({$expression}, CHAR(9), '')";
    }
}
