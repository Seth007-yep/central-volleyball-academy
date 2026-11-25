<?php

declare(strict_types=1);

namespace Cva\Repositories;

use PDO;

/** Write a small allowlisted record of important staff-access events. */
final class AuditLogRepository
{
    public function __construct(private PDO $database)
    {
    }

    public function record(
        ?int $actorId,
        string $action,
        ?string $entityId = null,
        string $entityType = 'user'
    ): void
    {
        $statement = $this->database->prepare(
            'INSERT INTO audit_logs (actor_user_id, action, entity_type, entity_id)
             VALUES (:actor_user_id, :action, :entity_type, :entity_id)'
        );
        $statement->execute([
            'actor_user_id' => $actorId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
        ]);
    }

    /** Return a small newest-first slice for the admin dashboard. */
    public function recent(int $limit = 10): array
    {
        $limit = max(1, min($limit, 25));
        $statement = $this->database->prepare(
            'SELECT audit_logs.action, audit_logs.created_at, users.display_name AS actor_name
             FROM audit_logs
             LEFT JOIN users ON users.id = audit_logs.actor_user_id
             ORDER BY audit_logs.created_at DESC, audit_logs.id DESC
             LIMIT :row_limit'
        );
        $statement->bindValue(':row_limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    /** Count stored administrative events, optionally narrowed to an action. */
    public function countForAdminList(?string $action): int
    {
        if ($action === null) {
            return (int) $this->database->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn();
        }

        $statement = $this->database->prepare(
            'SELECT COUNT(*) FROM audit_logs WHERE action = :action'
        );
        $statement->execute(['action' => $action]);

        return (int) $statement->fetchColumn();
    }

    /** Return one newest-first slice; limit and offset are always integers. */
    public function pageForAdminList(?string $action, int $limit, int $offset): array
    {
        $filter = $action === null ? '' : 'WHERE audit_logs.action = :action';
        $statement = $this->database->prepare(
            'SELECT audit_logs.action, audit_logs.entity_type,
                    audit_logs.created_at, users.display_name AS actor_name
             FROM audit_logs
             LEFT JOIN users ON users.id = audit_logs.actor_user_id
             ' . $filter . '
             ORDER BY audit_logs.created_at DESC, audit_logs.id DESC
             LIMIT :row_limit OFFSET :row_offset'
        );

        if ($action !== null) {
            $statement->bindValue(':action', $action, PDO::PARAM_STR);
        }
        $statement->bindValue(':row_limit', max(1, min($limit, 100)), PDO::PARAM_INT);
        $statement->bindValue(':row_offset', max(0, $offset), PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }
}
