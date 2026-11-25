<?php

declare(strict_types=1);

namespace Cva\Controllers\Admin;

use Cva\Repositories\AuditLogRepository;
use Cva\Support\Database;

/** Show privacy-conscious administrative history with server-side pagination. */
final class AuditLogController
{
    private const PAGE_SIZE = 25;

    public const ACTION_LABELS = [
        'admin.login.failed' => 'Sign-in attempt not verified',
        'admin.login.success' => 'Staff member signed in',
        'admin.logout' => 'Staff member signed out',
        'setup.admin.created' => 'Initial administrator created',
    ];

    public function index(): void
    {
        $settings = require dirname(__DIR__, 3) . '/config/database.php';
        $audit = new AuditLogRepository(Database::connect($settings));
        $rawAction = $_GET['action'] ?? 'all';
        $action = is_string($rawAction) && array_key_exists($rawAction, self::ACTION_LABELS)
            ? $rawAction
            : 'all';
        $filterAction = $action === 'all' ? null : $action;
        $totalRows = $audit->countForAdminList($filterAction);
        $totalPages = max(1, (int) ceil($totalRows / self::PAGE_SIZE));
        $rawPage = $_GET['page'] ?? '1';
        $requestedPage = is_string($rawPage) || is_int($rawPage)
            ? filter_var($rawPage, FILTER_VALIDATE_INT)
            : false;
        $page = max(1, min($totalPages, $requestedPage === false ? 1 : $requestedPage));
        $auditEvents = $audit->pageForAdminList(
            $filterAction,
            self::PAGE_SIZE,
            ($page - 1) * self::PAGE_SIZE
        );

        $firstPage = max(1, $page - 2);
        $lastPage = min($totalPages, $firstPage + 4);
        $firstPage = max(1, $lastPage - 4);
        $paginationPages = range($firstPage, $lastPage);

        require dirname(__DIR__, 2) . '/Views/admin/audit-logs.php';
    }
}
