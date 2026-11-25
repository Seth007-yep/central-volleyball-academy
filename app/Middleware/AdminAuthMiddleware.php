<?php

declare(strict_types=1);

namespace Cva\Middleware;

use Cva\Repositories\UserRepository;
use Cva\Support\Database;
use Cva\Support\Url;

/** Enforce current account status and the minimum role for each protected route. */
final class AdminAuthMiddleware
{
    public static function authorize(string $requiredAccess = 'staff'): bool
    {
        $user = \Cva\Support\Session::authenticatedUser();

        if ($user === null) {
            header('Location: ' . Url::path('/admin/login'), true, 303);
            return false;
        }

        $settings = require dirname(__DIR__, 2) . '/config/database.php';
        $database = Database::connect($settings);

        $users = new UserRepository($database);
        $isAdministrator = $users->isActiveAdministrator($user['id']);
        $hasAccess = $requiredAccess === 'administrator'
            ? $isAdministrator
            : $users->isActiveStaffMember($user['id']);

        if (!$hasAccess) {
            \Cva\Support\Session::destroy();
            header('Location: ' . Url::path('/admin/login'), true, 303);
            return false;
        }

        // Navigation uses this hint, but each protected route checks the database above.
        \Cva\Support\Session::setAdministratorFlag($isAdministrator);

        return true;
    }
}
