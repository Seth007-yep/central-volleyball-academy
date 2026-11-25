<?php

declare(strict_types=1);

// Read-only integration checks for the admin list queries. Run with:
// C:\xampp\php\php.exe tests\admin-read-models-smoke.php
// The script uses the local cva_app account and does not create or change rows.
$projectRoot = dirname(__DIR__);
require $projectRoot . '/bootstrap/autoload.php';

use Cva\Repositories\AuditLogRepository;
use Cva\Repositories\CoachRepository;
use Cva\Repositories\UserRepository;
use Cva\Support\Database;

/** Fail with a useful check name without printing data from the database. */
function expectSmokeCheck(bool $condition, string $checkName): void
{
    if (!$condition) {
        throw new RuntimeException('Failed check: ' . $checkName);
    }
}

try {
    $settings = require $projectRoot . '/config/database.php';
    $database = Database::connect($settings);
    $users = new UserRepository($database);
    $audit = new AuditLogRepository($database);
    $coaches = new CoachRepository($database);

    $activeCount = $users->countActive();
    expectSmokeCheck($activeCount >= 0, 'active staff count');
    expectSmokeCheck(
        $users->countForAdminList('active', '') === $activeCount,
        'active status filter matches summary count'
    );

    // A fresh marker checks empty search results without relying on live names.
    $marker = 'smoke-' . bin2hex(random_bytes(12));
    expectSmokeCheck(
        $users->countForAdminList(null, $marker) === 0,
        'non-matching staff search'
    );
    expectSmokeCheck(
        $users->pageForAdminList(null, $marker, 25, 0) === [],
        'empty staff result page'
    );

    $staffPage = $users->pageForAdminList(null, '', 25, 0);
    expectSmokeCheck(count($staffPage) <= 25, 'staff page size is bounded');
    foreach ($staffPage as $staff) {
        expectSmokeCheck(
            isset($staff['id'], $staff['display_name'], $staff['email'], $staff['status']),
            'staff rows contain only required list fields'
        );
    }

    $allEvents = $audit->countForAdminList(null);
    expectSmokeCheck($allEvents >= 0, 'audit event count');
    $eventPage = $audit->pageForAdminList(null, 25, 0);
    expectSmokeCheck(count($eventPage) <= 25, 'audit page size is bounded');
    foreach ($eventPage as $event) {
        expectSmokeCheck(
            isset($event['action'], $event['entity_type'], $event['created_at']),
            'audit rows contain the display fields'
        );
    }

    $loginEvents = $audit->pageForAdminList('admin.login.success', 25, 0);
    foreach ($loginEvents as $event) {
        expectSmokeCheck(
            $event['action'] === 'admin.login.success',
            'audit action filter is applied in the database'
        );
    }

    // Coach list and public read queries must work with empty or populated tables.
    expectSmokeCheck($coaches->countForAdminList(null, '') >= 0, 'coach profile count');
    $coachMarker = 'no-match-' . bin2hex(random_bytes(12));
    expectSmokeCheck(
        $coaches->countForAdminList(null, $coachMarker) === 0,
        'non-matching coach search'
    );
    expectSmokeCheck(
        $coaches->pageForAdminList(null, $coachMarker, 25, 0) === [],
        'empty coach result page'
    );
    expectSmokeCheck(
        count($coaches->pageForAdminList(null, '', 25, 0)) <= 25,
        'coach page size is bounded'
    );
    expectSmokeCheck($coaches->publishedCount() >= 0, 'published coach summary count');
    foreach ($coaches->publishedForPublic() as $coach) {
        expectSmokeCheck(
            isset($coach['first_name'], $coach['last_name'], $coach['role_title'], $coach['biography'], $coach['experience']),
            'public coach rows contain the profile fields'
        );
    }

    fwrite(STDOUT, "Admin read-model checks passed, including coach profiles. No rows were changed.\n");
} catch (Throwable $error) {
    fwrite(STDERR, 'Admin read-model check failed (' . get_class($error) . ').' . "\n");
    exit(1);
}
