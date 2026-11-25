<?php

declare(strict_types=1);

namespace Cva\Repositories;

use PDO;

/** Store event details and keep unpublished activities out of public results. */
final class EventRepository
{
    public function __construct(private PDO $database)
    {
    }

    public function countForAdminList(?string $status, string $search): int
    {
        [$where, $parameters] = $this->filters($status, $search);
        $statement = $this->database->prepare('SELECT COUNT(*) FROM events ' . $where);
        $statement->execute($parameters);

        return (int) $statement->fetchColumn();
    }

    public function pageForAdminList(?string $status, string $search, int $limit, int $offset): array
    {
        [$where, $parameters] = $this->filters($status, $search);
        $statement = $this->database->prepare(
            'SELECT id, title, starts_at, ends_at, location,
                    registration_status, status
             FROM events ' . $where . '
             ORDER BY starts_at DESC, id DESC
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
            'SELECT id, title, description, starts_at, ends_at, location,
                    registration_status, photo_gallery_item_id, status, published_at
             FROM events WHERE id = :id'
        );
        $statement->execute(['id' => $id]);
        $event = $statement->fetch();

        return is_array($event) ? $event : null;
    }

    public function save(array $event, ?int $id = null): int
    {
        unset($event['id']);
        if ($id === null) {
            $statement = $this->database->prepare(
                'INSERT INTO events (
                    title, description, starts_at, ends_at, location,
                    registration_status, photo_gallery_item_id, status, published_at
                 ) VALUES (
                    :title, :description, :starts_at, :ends_at, :location,
                    :registration_status, :photo_gallery_item_id, :status,
                    IF(:publish_on_create = 1, CURRENT_TIMESTAMP, NULL)
                 )'
            );
            $event['publish_on_create'] = $event['status'] === 'published' ? 1 : 0;
            $statement->execute($event);

            return (int) $this->database->lastInsertId();
        }

        $event['id'] = $id;
        $event['publish_now'] = $event['status'] === 'published' ? 1 : 0;
        $statement = $this->database->prepare(
            'UPDATE events SET title = :title, description = :description,
                starts_at = :starts_at, ends_at = :ends_at, location = :location,
                registration_status = :registration_status,
                photo_gallery_item_id = :photo_gallery_item_id,
                status = :status,
                published_at = IF(published_at IS NULL AND :publish_now = 1, CURRENT_TIMESTAMP, published_at)
             WHERE id = :id'
        );
        $statement->execute($event);

        return $id;
    }

    /** Upcoming published events and previously published cancellations are public. */
    public function upcomingForPublic(int $limit = 6): array
    {
        $statement = $this->database->prepare(
            "SELECT events.id, events.title, events.description, events.starts_at,
                    events.ends_at, events.location, events.registration_status,
                    events.status, gallery_items.id AS photo_id,
                    gallery_items.alternative_text AS photo_alt
             FROM events
             LEFT JOIN gallery_items
               ON gallery_items.id = events.photo_gallery_item_id
              AND gallery_items.status = 'published'
              AND gallery_items.media_kind = 'image'
             WHERE events.starts_at >= CURRENT_TIMESTAMP
               AND (events.status = 'published'
                    OR (events.status = 'cancelled' AND events.published_at IS NOT NULL))
             ORDER BY events.starts_at, events.id
             LIMIT :row_limit"
        );
        $statement->bindValue(':row_limit', max(1, min(12, $limit)), PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    public function upcomingCount(): int
    {
        return (int) $this->database->query(
            "SELECT COUNT(*) FROM events
             WHERE status = 'published' AND starts_at >= CURRENT_TIMESTAMP"
        )->fetchColumn();
    }

    public function publishedCount(): int
    {
        return (int) $this->database
            ->query("SELECT COUNT(*) FROM events WHERE status = 'published'")
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
            $clauses[] = '(title LIKE :search_title OR location LIKE :search_location)';
            $pattern = '%' . $search . '%';
            $parameters[':search_title'] = $pattern;
            $parameters[':search_location'] = $pattern;
        }

        return [$clauses === [] ? '' : 'WHERE ' . implode(' AND ', $clauses), $parameters];
    }
}
