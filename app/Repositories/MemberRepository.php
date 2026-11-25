<?php

declare(strict_types=1);

namespace Cva\Repositories;

use PDO;

/** Keep player/member administration paginated and limited to required fields. */
final class MemberRepository
{
    public function __construct(private PDO $database)
    {
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
        $statement = $this->database->prepare('SELECT COUNT(*) FROM members ' . $where);
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
             FROM members ' . $where . '
             ORDER BY last_name, first_name, id
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
                    guardian_relationship, guardian_email, guardian_phone, status
             FROM members WHERE id = :id'
        );
        $statement->execute(['id' => $id]);
        $member = $statement->fetch();

        return is_array($member) ? $member : null;
    }

    public function update(int $id, array $member): void
    {
        $member['id'] = $id;
        $statement = $this->database->prepare(
            'UPDATE members SET
                first_name = :first_name,
                last_name = :last_name,
                date_of_birth = :date_of_birth,
                email = :email,
                phone = :phone,
                program_interest = :program_interest,
                previous_experience = :previous_experience,
                guardian_name = :guardian_name,
                guardian_relationship = :guardian_relationship,
                guardian_email = :guardian_email,
                guardian_phone = :guardian_phone,
                status = :status
             WHERE id = :id'
        );
        $statement->execute($member);
    }

    public function activeCount(): int
    {
        return (int) $this->database
            ->query("SELECT COUNT(*) FROM members WHERE status = 'active'")
            ->fetchColumn();
    }

    /** Members are created only from approved applications; source links record that provenance. */
    public function approvedCount(): int
    {
        return (int) $this->database
            ->query('SELECT COUNT(*) FROM members WHERE source_registration_id IS NOT NULL')
            ->fetchColumn();
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
            $clauses[] = '(' . $this->normalizedPhoneSql('phone') . ' LIKE :phone_player'
                . ' OR ' . $this->normalizedPhoneSql('guardian_phone') . ' LIKE :phone_guardian)';
            $parameters[':phone_player'] = '%' . $phone . '%';
            $parameters[':phone_guardian'] = '%' . $phone . '%';
        }
        if ($program !== '') {
            $clauses[] = 'program_interest LIKE :program_interest';
            $parameters[':program_interest'] = '%' . $program . '%';
        }
        if ($dateFrom !== null) {
            $clauses[] = 'date_of_birth >= :birth_date_from';
            $parameters[':birth_date_from'] = $dateFrom;
        }
        if ($dateTo !== null) {
            $clauses[] = 'date_of_birth <= :birth_date_to';
            $parameters[':birth_date_to'] = $dateTo;
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
