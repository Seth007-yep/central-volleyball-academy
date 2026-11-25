<?php

declare(strict_types=1);

namespace Cva\Repositories;

use PDO;

/** Store staff-managed coach profiles and expose only published profiles publicly. */
final class CoachRepository
{
    public function __construct(private PDO $database)
    {
    }

    public function countForAdminList(?string $status, string $search): int
    {
        [$where, $parameters] = $this->filters($status, $search);
        $statement = $this->database->prepare('SELECT COUNT(*) FROM coaches ' . $where);
        $statement->execute($parameters);

        return (int) $statement->fetchColumn();
    }

    public function pageForAdminList(?string $status, string $search, int $limit, int $offset): array
    {
        [$where, $parameters] = $this->filters($status, $search);
        $statement = $this->database->prepare(
            'SELECT id, first_name, last_name, role_title, status, display_order
             FROM coaches ' . $where . '
             ORDER BY display_order, last_name, first_name, id
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
            'SELECT id, first_name, last_name, role_title, biography, experience, qualifications,
                    photo_gallery_item_id, display_order, status
             FROM coaches WHERE id = :id'
        );
        $statement->execute(['id' => $id]);
        $coach = $statement->fetch();

        return is_array($coach) ? $coach : null;
    }

    public function save(array $coach, ?int $id = null): int
    {
        if ($id === null) {
            $statement = $this->database->prepare(
                'INSERT INTO coaches (
                    first_name, last_name, role_title, biography, experience, qualifications,
                    photo_gallery_item_id, display_order, status
                 ) VALUES (
                    :first_name, :last_name, :role_title, :biography, :experience, :qualifications,
                    :photo_gallery_item_id, :display_order, :status
                 )'
            );
            $statement->execute($coach);

            return (int) $this->database->lastInsertId();
        }

        $coach['id'] = $id;
        $statement = $this->database->prepare(
            'UPDATE coaches SET first_name = :first_name, last_name = :last_name,
                role_title = :role_title, biography = :biography, experience = :experience,
                qualifications = :qualifications,
                photo_gallery_item_id = :photo_gallery_item_id, display_order = :display_order,
                status = :status WHERE id = :id'
        );
        $statement->execute($coach);

        return $id;
    }

    public function publishedForPublic(): array
    {
        $statement = $this->database->query(
            "SELECT coaches.id, coaches.first_name, coaches.last_name, coaches.role_title,
                    coaches.biography, coaches.experience, coaches.qualifications,
                    gallery_items.id AS photo_id, gallery_items.alternative_text AS photo_alt
             FROM coaches
             LEFT JOIN gallery_items
               ON gallery_items.id = coaches.photo_gallery_item_id
              AND gallery_items.status = 'published'
              AND gallery_items.media_kind = 'image'
             WHERE coaches.status = 'published'
             ORDER BY coaches.display_order, coaches.last_name, coaches.first_name, coaches.id"
        );

        return $statement->fetchAll();
    }

    public function publishedCount(): int
    {
        return (int) $this->database
            ->query("SELECT COUNT(*) FROM coaches WHERE status = 'published'")
            ->fetchColumn();
    }

    /** Return the published names staff may assign to public programs. */
    public function publishedForProgramOptions(): array
    {
        $statement = $this->database->query(
            "SELECT id, first_name, last_name, role_title
             FROM coaches WHERE status = 'published'
             ORDER BY display_order, last_name, first_name, id"
        );

        return $statement->fetchAll();
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
            $clauses[] = '(first_name LIKE :search_first OR last_name LIKE :search_last OR role_title LIKE :search_role)';
            $pattern = '%' . $search . '%';
            $parameters[':search_first'] = $pattern;
            $parameters[':search_last'] = $pattern;
            $parameters[':search_role'] = $pattern;
        }

        return [$clauses === [] ? '' : 'WHERE ' . implode(' AND ', $clauses), $parameters];
    }
}
