<?php

declare(strict_types=1);

namespace Cva\Controllers\Public;

use Cva\Repositories\AcademyContentRepository;
use Cva\Repositories\CoachRepository;
use Cva\Repositories\EventRepository;
use Cva\Repositories\GalleryRepository;
use Cva\Repositories\ProgramRepository;
use Cva\Support\Database;

/** Load only approved public content for the academy homepage. */
final class HomeController
{
    public function index(): void
    {
        $projectRoot = dirname(__DIR__, 3);
        $appConfig = require $projectRoot . '/config/app.php';
        $settings = require $projectRoot . '/config/database.php';
        $database = Database::connect($settings);
        $siteContent = (new AcademyContentRepository($database))->current();
        $galleryItems = (new GalleryRepository($database))->pageForPublic();
        $coaches = (new CoachRepository($database))->publishedForPublic();
        $programRepository = new ProgramRepository($database);
        $programs = $programRepository->publishedForPublic();
        $programIds = array_map(static fn (array $program): int => (int) $program['id'], $programs);
        $programSchedules = [];
        foreach ($programRepository->publicSchedulesFor($programIds) as $schedule) {
            $programSchedules[(int) $schedule['program_id']][] = $schedule['schedule_text'];
        }
        $programCoaches = [];
        foreach ($programRepository->publicCoachesFor($programIds) as $coach) {
            $programCoaches[(int) $coach['program_id']][] = $coach;
        }
        $events = (new EventRepository($database))->upcomingForPublic();
        $mediaBasePath = \Cva\Support\Url::path('/gallery/media');

        require dirname(__DIR__, 2) . '/Views/public/home.php';
    }
}
