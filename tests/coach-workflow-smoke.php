<?php

declare(strict_types=1);

// Uses one synthetic coach inside a transaction and always rolls it back.
// Run with C:\xampp\php\php.exe tests\coach-workflow-smoke.php
$projectRoot = dirname(__DIR__);
require $projectRoot . '/bootstrap/autoload.php';

use Cva\Repositories\CoachRepository;
use Cva\Support\Database;

/** Fail with a short check name without printing database or user information. */
function expectCoachCheck(bool $condition, string $checkName): void
{
    if (!$condition) {
        throw new RuntimeException('Failed check: ' . $checkName);
    }
}

try {
    $settings = require $projectRoot . '/config/database.php';
    $database = Database::connect($settings);
    $repository = new CoachRepository($database);
    $database->beginTransaction();

    try {
        $marker = 'Smoke' . bin2hex(random_bytes(8));
        $profile = [
            'first_name' => $marker,
            'last_name' => 'Temporary',
            'role_title' => 'Smoke Test Coach',
            'biography' => 'Synthetic profile used only in this rolled-back smoke check.',
            'experience' => 'Synthetic coaching experience for testing.',
            'qualifications' => 'Synthetic qualification for testing.',
            'photo_gallery_item_id' => null,
            'display_order' => 9999,
            'status' => 'draft',
        ];
        $id = $repository->save($profile);
        $draft = $repository->findForAdmin($id);
        expectCoachCheck(is_array($draft) && $draft['status'] === 'draft', 'new profile starts as a draft');

        $profile['status'] = 'published';
        $repository->save($profile, $id);
        $publishedIds = array_map(
            static fn (array $coach): int => (int) $coach['id'],
            $repository->publishedForPublic()
        );
        expectCoachCheck(in_array($id, $publishedIds, true), 'published profile is publicly readable');
        $publicProfiles = $repository->publishedForPublic();
        $publicProfile = null;
        foreach ($publicProfiles as $candidate) {
            if ((int) $candidate['id'] === $id) {
                $publicProfile = $candidate;
                break;
            }
        }
        expectCoachCheck(
            is_array($publicProfile) && $publicProfile['experience'] === $profile['experience'],
            'academy-approved coach experience is publicly readable'
        );

        $profile['status'] = 'archived';
        $repository->save($profile, $id);
        $archivedIds = array_map(
            static fn (array $coach): int => (int) $coach['id'],
            $repository->publishedForPublic()
        );
        expectCoachCheck(!in_array($id, $archivedIds, true), 'archived profile is hidden publicly');
    } finally {
        if ($database->inTransaction()) {
            $database->rollBack();
        }
    }

    fwrite(STDOUT, "Coach workflow smoke checks passed (4 checks); synthetic row rolled back.\n");
} catch (Throwable $error) {
    fwrite(STDERR, 'Coach workflow check failed (' . get_class($error) . ').' . "\n");
    exit(1);
}
