<?php

declare(strict_types=1);

namespace Cva\Controllers\Admin;

use Cva\Repositories\AuditLogRepository;
use Cva\Services\AuthService;
use Cva\Support\Csrf;
use Cva\Support\Database;
use Cva\Support\Html;
use Cva\Support\Session;
use Cva\Support\Url;

/** Handle the staff sign-in form and authenticated sign-out action. */
final class AuthController
{
    public function showLogin(): void
    {
        Session::start();
        $this->renderLogin(null, '');
    }

    public function login(): void
    {
        Session::start();

        if (!Csrf::isValid($_POST['csrf_token'] ?? null)) {
            http_response_code(403);
            $this->renderLogin('Your sign-in form expired. Please try again.', '');
            return;
        }

        $email = isset($_POST['email']) && is_string($_POST['email'])
            ? $_POST['email']
            : '';
        $password = isset($_POST['password']) && is_string($_POST['password'])
            ? $_POST['password']
            : '';

        $settings = require dirname(__DIR__, 3) . '/config/database.php';
        $securitySettings = require dirname(__DIR__, 3) . '/config/security.php';
        $database = Database::connect($settings);
        $service = new AuthService($database, $securitySettings['app_key']);
        $result = $service->authenticate(
            $email,
            $password,
            (string) ($_SERVER['REMOTE_ADDR'] ?? '')
        );

        if ($result['status'] === 'authenticated' && is_array($result['user'])) {
            Session::signIn(
                $result['user']['id'],
                $result['user']['display_name'],
                (bool) ($result['user']['is_admin'] ?? false)
            );
            header('Location: ' . Url::path('/admin'), true, 303);
            return;
        }

        if ($result['status'] === 'rate_limited') {
            http_response_code(429);
            $this->renderLogin('Sign-in is temporarily unavailable. Wait a few minutes and try again.', $email);
            return;
        }

        http_response_code(401);
        $this->renderLogin('Sign-in failed. Check your details and try again.', $email);
    }

    public function logout(): void
    {
        Session::start();
        $user = Session::authenticatedUser();

        if ($user === null) {
            header('Location: ' . Url::path('/admin/login'), true, 303);
            return;
        }

        if (!Csrf::isValid($_POST['csrf_token'] ?? null)) {
            http_response_code(403);
            echo 'This request could not be verified. Return to the admin page and try again.';
            return;
        }

        $settings = require dirname(__DIR__, 3) . '/config/database.php';
        $database = Database::connect($settings);
        (new AuditLogRepository($database))->record(
            $user['id'],
            'admin.logout',
            (string) $user['id']
        );

        Session::destroy();
        header('Location: ' . Url::path('/admin/login'), true, 303);
    }

    private function renderLogin(?string $errorMessage, string $email): void
    {
        $appConfig = require dirname(__DIR__, 3) . '/config/app.php';
        $csrfToken = Csrf::token();
        require dirname(__DIR__, 2) . '/Views/admin/login.php';
    }
}
