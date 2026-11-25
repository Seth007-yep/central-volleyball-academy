<?php

declare(strict_types=1);

namespace Cva\Repositories;

use PDO;

/** Read and update the academy's single editable public information record. */
final class AcademyContentRepository
{
    public function __construct(private PDO $database)
    {
    }

    public function current(): array
    {
        $statement = $this->database->query(
            'SELECT introduction, about_text, mission, vision, coaching_philosophy,
                    contact_email, contact_phone, contact_location, updated_at
             FROM academy_content WHERE id = 1'
        );
        $content = $statement->fetch();

        return is_array($content) ? $content : [];
    }

    /** Save only known fields; callers validate and normalize each value first. */
    public function save(array $content): void
    {
        $statement = $this->database->prepare(
            'INSERT INTO academy_content (
                id, introduction, about_text, mission, vision,
                coaching_philosophy, contact_email, contact_phone, contact_location
             ) VALUES (
                1, :introduction, :about_text, :mission, :vision,
                :coaching_philosophy, :contact_email, :contact_phone, :contact_location
             ) ON DUPLICATE KEY UPDATE
                introduction = VALUES(introduction),
                about_text = VALUES(about_text),
                mission = VALUES(mission),
                vision = VALUES(vision),
                coaching_philosophy = VALUES(coaching_philosophy),
                contact_email = VALUES(contact_email),
                contact_phone = VALUES(contact_phone),
                contact_location = VALUES(contact_location)'
        );
        $statement->execute($content);
    }
}
