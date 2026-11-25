<?php

declare(strict_types=1);

namespace Cva\Repositories;

use PDO;

/** Store editable programs and fetch only published information for public pages. */
final class ProgramRepository
{
    public function __construct(private PDO $database)
    {
    }

    public function countForAdminList(?string $status, string $search): int
    {
        [$where, $parameters] = $this->filters($status, $search);
        $statement = $this->database->prepare('SELECT COUNT(*) FROM programs ' . $where);
        $statement->execute($parameters);

        return (int) $statement->fetchColumn();
    }

    public function pageForAdminList(?string $status, string $search, int $limit, int $offset): array
    {
        [$where, $parameters] = $this->filters($status, $search);
        $statement = $this->database->prepare(
            'SELECT id, title, category, age_group, fee_details, capacity, status, display_order
             FROM programs ' . $where . '
             ORDER BY display_order, title, id
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
            'SELECT id, title, category, age_group, description, fee_details,
                    capacity, display_order, status
             FROM programs WHERE id = :id'
        );
        $statement->execute(['id' => $id]);
        $program = $statement->fetch();

        return is_array($program) ? $program : null;
    }

    public function scheduleLines(int $programId): array
    {
        $statement = $this->database->prepare(
            'SELECT schedule_text FROM program_schedules
             WHERE program_id = :program_id ORDER BY display_order, id'
        );
        $statement->execute(['program_id' => $programId]);

        return array_column($statement->fetchAll(), 'schedule_text');
    }

    public function assignedCoachIds(int $programId): array
    {
        $statement = $this->database->prepare(
            'SELECT coach_id FROM program_coaches WHERE program_id = :program_id ORDER BY coach_id'
        );
        $statement->execute(['program_id' => $programId]);

        return array_map('intval', array_column($statement->fetchAll(), 'coach_id'));
    }

    public function save(array $program, ?int $id = null): int
    {
        if ($id === null) {
            $statement = $this->database->prepare(
                'INSERT INTO programs (
                    title, category, age_group, description, fee_details,
                    capacity, display_order, status
                 ) VALUES (
                    :title, :category, :age_group, :description, :fee_details,
                    :capacity, :display_order, :status
                 )'
            );
            $statement->execute($program);

            return (int) $this->database->lastInsertId();
        }

        $program['id'] = $id;
        $statement = $this->database->prepare(
            'UPDATE programs SET title = :title, category = :category,
                age_group = :age_group, description = :description,
                fee_details = :fee_details, capacity = :capacity,
                display_order = :display_order, status = :status
             WHERE id = :id'
        );
        $statement->execute($program);

        return $id;
    }

    /** Replace a program's small schedule list inside the caller's transaction. */
    public function replaceScheduleLines(int $programId, array $scheduleLines): void
    {
        $delete = $this->database->prepare('DELETE FROM program_schedules WHERE program_id = :program_id');
        $delete->execute(['program_id' => $programId]);
        $insert = $this->database->prepare(
            'INSERT INTO program_schedules (program_id, schedule_text, display_order)
             VALUES (:program_id, :schedule_text, :display_order)'
        );
        foreach (array_values($scheduleLines) as $order => $scheduleText) {
            $insert->execute([
                'program_id' => $programId,
                'schedule_text' => $scheduleText,
                'display_order' => $order,
            ]);
        }
    }

    /** Replace coach links; only published coaches are accepted by the controller. */
    public function replaceCoachAssignments(int $programId, array $coachIds): void
    {
        $delete = $this->database->prepare('DELETE FROM program_coaches WHERE program_id = :program_id');
        $delete->execute(['program_id' => $programId]);
        $insert = $this->database->prepare(
            'INSERT INTO program_coaches (program_id, coach_id)
             VALUES (:program_id, :coach_id)'
        );
        foreach (array_unique(array_map('intval', $coachIds)) as $coachId) {
            $insert->execute(['program_id' => $programId, 'coach_id' => $coachId]);
        }
    }

    public function publishedForPublic(): array
    {
        $statement = $this->database->query(
            "SELECT id, title, category, age_group, description, fee_details, capacity
             FROM programs WHERE status = 'published'
             ORDER BY display_order, title, id"
        );

        return $statement->fetchAll();
    }

    /** Fetch schedule rows for a set of IDs in one query, avoiding per-card queries. */
    public function publicSchedulesFor(array $programIds): array
    {
        [$inClause, $parameters] = $this->idListParameters($programIds, 'schedule_program');
        if ($inClause === '') {
            return [];
        }
        $statement = $this->database->prepare(
            'SELECT program_id, schedule_text FROM program_schedules
             WHERE program_id IN (' . $inClause . ')
             ORDER BY program_id, display_order, id'
        );
        foreach ($parameters as $name => $id) {
            $statement->bindValue($name, $id, PDO::PARAM_INT);
        }
        $statement->execute();

        return $statement->fetchAll();
    }

    /** Fetch only published coach profiles assigned to published programs. */
    public function publicCoachesFor(array $programIds): array
    {
        [$inClause, $parameters] = $this->idListParameters($programIds, 'coach_program');
        if ($inClause === '') {
            return [];
        }
        $statement = $this->database->prepare(
            "SELECT program_coaches.program_id, coaches.first_name, coaches.last_name, coaches.role_title
             FROM program_coaches
             INNER JOIN programs ON programs.id = program_coaches.program_id
                               AND programs.status = 'published'
             INNER JOIN coaches ON coaches.id = program_coaches.coach_id
                               AND coaches.status = 'published'
             WHERE program_coaches.program_id IN (" . $inClause . ")
             ORDER BY program_coaches.program_id, coaches.display_order, coaches.last_name, coaches.first_name"
        );
        foreach ($parameters as $name => $id) {
            $statement->bindValue($name, $id, PDO::PARAM_INT);
        }
        $statement->execute();

        return $statement->fetchAll();
    }

    public function publishedCount(): int
    {
        return (int) $this->database
            ->query("SELECT COUNT(*) FROM programs WHERE status = 'published'")
            ->fetchColumn();
    }

    private function filters(?string $status, string $search): array
    {
        $clauses = [];
        $parameters = [];
        if ($status !== null) {
            $clauses[] = 'status = :status';
            $parameters[':status'] = $status;
        }
        if ($search !== '') {
            $clauses[] = '(title LIKE :search_title OR category LIKE :search_category OR age_group LIKE :search_age_group)';
            $pattern = '%' . $search . '%';
            $parameters[':search_title'] = $pattern;
            $parameters[':search_category'] = $pattern;
            $parameters[':search_age_group'] = $pattern;
        }

        return [$clauses === [] ? '' : 'WHERE ' . implode(' AND ', $clauses), $parameters];
    }

    /** Bind database-derived IDs as integers instead of inserting them into SQL. */
    private function idListParameters(array $ids, string $prefix): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return ['', []];
        }
        $names = [];
        $parameters = [];
        foreach ($ids as $index => $id) {
            $name = ':' . $prefix . '_' . $index;
            $names[] = $name;
            $parameters[$name] = $id;
        }

        return [implode(', ', $names), $parameters];
    }
}
