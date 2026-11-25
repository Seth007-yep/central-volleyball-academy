<?php

declare(strict_types=1);

namespace Cva\Controllers\Admin;

use Cva\Repositories\AuditLogRepository;
use Cva\Repositories\CoachRepository;
use Cva\Repositories\EventRepository;
use Cva\Repositories\MemberRepository;
use Cva\Repositories\ProgramRepository;
use Cva\Repositories\RegistrationRepository;
use Cva\Repositories\UserRepository;
use Cva\Support\Csrf;
use Cva\Support\Database;
use Cva\Support\Session;

/** Render the first protected admin landing page after successful sign-in. */
final class DashboardController
{
    public function index(): void
    {
        $user = Session::authenticatedUser();
        $csrfToken = Csrf::token();
        $settings = require dirname(__DIR__, 3) . '/config/database.php';
        $database = Database::connect($settings);
        $activeStaffCount = (new UserRepository($database))->countActive();
        $memberRepository = new MemberRepository($database);
        $activePlayerCount = $memberRepository->activeCount();
        $totalMemberCount = $memberRepository->countForAdminList(null, '');
        $approvedMemberCount = $memberRepository->approvedCount();
        $publishedCoachCount = (new CoachRepository($database))->publishedCount();
        $pendingRegistrationCount = (new RegistrationRepository($database))->countForAdminList('pending', '');
        $activeProgramCount = (new ProgramRepository($database))->publishedCount();
        $upcomingEventCount = (new EventRepository($database))->upcomingCount();
        $canViewAudit = (bool) ($user['is_admin'] ?? false);
        $recentActivity = $canViewAudit ? (new AuditLogRepository($database))->recent(10) : [];
        $adminPage = 'dashboard';
        require dirname(__DIR__, 2) . '/Views/admin/dashboard.php';
    }
}
