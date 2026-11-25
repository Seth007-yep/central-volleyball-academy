<?php

declare(strict_types=1);

namespace Cva\Repositories;

use PDO;

/** Store gallery metadata separately from media bytes kept outside public/. */
final class GalleryRepository
{
    public function __construct(private PDO $database)
    {
    }

    public function create(array $item): int
    {
        $statement = $this->database->prepare(
            'INSERT INTO gallery_items (
                storage_key, media_kind, mime_type, file_size,
                title, alternative_text, caption, uploaded_by_user_id
             ) VALUES (
                :storage_key, :media_kind, :mime_type, :file_size,
                :title, :alternative_text, :caption, :uploaded_by_user_id
             )'
        );
        $statement->execute($item);

        return (int) $this->database->lastInsertId();
    }

    public function pageForPublic(int $limit = 24): array
    {
        $statement = $this->database->prepare(
            "SELECT id, media_kind, mime_type, title, alternative_text, caption
             FROM gallery_items WHERE status = 'published'
             ORDER BY created_at DESC, id DESC LIMIT :row_limit"
        );
        $statement->bindValue(':row_limit', max(1, min(48, $limit)), PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    /** Only already-published still images can be chosen for a coach profile. */
    public function publishedImages(): array
    {
        $statement = $this->database->query(
            "SELECT id, title, alternative_text
             FROM gallery_items
             WHERE status = 'published' AND media_kind = 'image'
             ORDER BY created_at DESC, id DESC"
        );

        return $statement->fetchAll();
    }

    public function pageForAdmin(int $limit = 50): array
    {
        $statement = $this->database->prepare(
            'SELECT id, media_kind, mime_type, title, alternative_text,
                    caption, status, created_at
             FROM gallery_items ORDER BY created_at DESC, id DESC LIMIT :row_limit'
        );
        $statement->bindValue(':row_limit', max(1, min(100, $limit)), PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    public function updateDetails(int $id, string $title, string $alternativeText, ?string $caption, string $status): void
    {
        $statement = $this->database->prepare(
            'UPDATE gallery_items SET title = :title, alternative_text = :alternative_text,
                caption = :caption, status = :status WHERE id = :id'
        );
        $statement->execute([
            'title' => $title,
            'alternative_text' => $alternativeText,
            'caption' => $caption,
            'status' => $status,
            'id' => $id,
        ]);
    }

    public function findPublished(int $id): ?array
    {
        $statement = $this->database->prepare(
            "SELECT storage_key, media_kind, mime_type, file_size
             FROM gallery_items WHERE id = :id AND status = 'published'"
        );
        $statement->execute(['id' => $id]);
        $item = $statement->fetch();

        return is_array($item) ? $item : null;
    }

    /** Admin-only media previews may include draft or archived items. */
    public function findForAdmin(int $id): ?array
    {
        $statement = $this->database->prepare(
            'SELECT storage_key, media_kind, mime_type, file_size, status
             FROM gallery_items WHERE id = :id'
        );
        $statement->execute(['id' => $id]);
        $item = $statement->fetch();

        return is_array($item) ? $item : null;
    }
}
