<?php

declare(strict_types=1);

// Exercise the new data workflows in one transaction, then roll every test row back.
// Run with: C:\xampp\php\php.exe tests\program-event-workflow-smoke.php
$projectRoot = dirname(__DIR__);
require $projectRoot . '/bootstrap/autoload.php';

use Cva\Repositories\EventRepository;
use Cva\Repositories\ProgramRepository;
use Cva\Support\Database;

/** Fail with a named check and never print database values. */
function expectProgramEventCheck(bool $condition, string $checkName): void
{
    if (!$condition) {
        throw new RuntimeException('Failed check: ' . $checkName);
    }
}

$database = null;

try {
    $settings = require $projectRoot . '/config/database.php';
    $database = Database::connect($settings);
    $database->beginTransaction();

    $programs = new ProgramRepository($database);
    $programId = $programs->save([
        'title' => 'Smoke test program ' . bin2hex(random_bytes(8)),
        'category' => 'Smoke test',
        'age_group' => null,
        'description' => 'Temporary test data, rolled back at the end.',
        'fee_details' => null,
        'capacity' => null,
        'display_order' => 9999,
        'status' => 'draft',
    ]);
    $programs->replaceScheduleLines($programId, ['Temporary schedule note']);
    $programs->replaceCoachAssignments($programId, []);

    $draftIds = array_column($programs->publishedForPublic(), 'id');
    expectProgramEventCheck(!in_array($programId, array_map('intval', $draftIds), true), 'draft programs stay private');
    expectProgramEventCheck($programs->scheduleLines($programId) === ['Temporary schedule note'], 'program schedule rows are saved');

    $programs->save([
        'title' => 'Smoke test program',
        'category' => 'Smoke test',
        'age_group' => null,
        'description' => 'Temporary test data, rolled back at the end.',
        'fee_details' => null,
        'capacity' => null,
        'display_order' => 9999,
        'status' => 'published',
    ], $programId);
    $publishedProgramIds = array_map('intval', array_column($programs->publishedForPublic(), 'id'));
    expectProgramEventCheck(in_array($programId, $publishedProgramIds, true), 'published programs appear publicly');
    expectProgramEventCheck(count($programs->publicSchedulesFor([$programId])) === 1, 'published schedule query works');

    $programs->save([
        'title' => 'Smoke test program',
        'category' => 'Smoke test',
        'age_group' => null,
        'description' => 'Temporary test data, rolled back at the end.',
        'fee_details' => null,
        'capacity' => null,
        'display_order' => 9999,
        'status' => 'archived',
    ], $programId);
    $archivedProgramIds = array_map('intval', array_column($programs->publishedForPublic(), 'id'));
    expectProgramEventCheck(!in_array($programId, $archivedProgramIds, true), 'archived programs stay private');

    $events = new EventRepository($database);
    $start = (new DateTimeImmutable('+2 days'))->setTime(12, 0);
    $end = $start->modify('+90 minutes');
    $event = [
        'title' => 'Temporary smoke test event',
        'description' => 'Temporary test data, rolled back at the end.',
        'starts_at' => $start->format('Y-m-d H:i:s'),
        'ends_at' => $end->format('Y-m-d H:i:s'),
        'location' => 'Temporary test location',
        'registration_status' => 'not_required',
        'photo_gallery_item_id' => null,
        'status' => 'draft',
    ];
    $eventId = $events->save($event);
    $publicEventIds = static function () use ($events): array {
        return array_map('intval', array_column($events->upcomingForPublic(12), 'id'));
    };
    expectProgramEventCheck(!in_array($eventId, $publicEventIds(), true), 'draft events stay private');

    $event['status'] = 'published';
    $events->save($event, $eventId);
    expectProgramEventCheck(in_array($eventId, $publicEventIds(), true), 'published upcoming events appear publicly');
    $publishedEvent = $events->findForAdmin($eventId);
    expectProgramEventCheck(is_array($publishedEvent) && $publishedEvent['published_at'] !== null, 'publication time is retained for later audit decisions');

    $event['description'] = 'Edited after publication for the smoke check.';
    $events->save($event, $eventId);
    $publishedEvent = $events->findForAdmin($eventId);
    expectProgramEventCheck(is_array($publishedEvent) && $publishedEvent['published_at'] !== null, 'ordinary edits retain the original publication time');

    $event['status'] = 'cancelled';
    $events->save($event, $eventId);
    $cancelledEvent = null;
    foreach ($events->upcomingForPublic(12) as $publicEvent) {
        if ((int) $publicEvent['id'] === $eventId) {
            $cancelledEvent = $publicEvent;
            break;
        }
    }
    expectProgramEventCheck(
        is_array($cancelledEvent) && $cancelledEvent['status'] === 'cancelled',
        'published cancellations remain visible with cancellation status'
    );

    $event['status'] = 'archived';
    $events->save($event, $eventId);
    expectProgramEventCheck(!in_array($eventId, $publicEventIds(), true), 'archived events stay private');

    $database->rollBack();
    fwrite(STDOUT, "Program and event workflow checks passed. Temporary rows were rolled back.\n");
} catch (Throwable $error) {
    if ($database instanceof PDO && $database->inTransaction()) {
        $database->rollBack();
    }
    fwrite(STDERR, 'Program/event workflow check failed (' . get_class($error) . ').' . "\n");
    exit(1);
}
