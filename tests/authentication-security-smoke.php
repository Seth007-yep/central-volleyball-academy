<?php

declare(strict_types=1);

// Synthetic users and rate-limit rows live in one transaction and are rolled back.
// Run with: C:\xampp\php\php.exe tests\authentication-security-smoke.php
$projectRoot = dirname(__DIR__);
require $projectRoot . '/bootstrap/autoload.php';

use Cva\Repositories\UserRepository;
use Cva\Services\AuthService;
use Cva\Support\Csrf;
use Cva\Support\Database;
use Cva\Support\Session;

function expectAuthenticationCheck(bool $condition, string $checkName): void
{
    if (!$condition) {
        throw new RuntimeException('Failed check: ' . $checkName);
    }
}

try {
    $settings = require $projectRoot . '/config/database.php';
    $database = Database::connect($settings);
    $users = new UserRepository($database);
    $auth = new AuthService($database, bin2hex(random_bytes(32)));
    $marker = bin2hex(random_bytes(10));
    $password = 'Synthetic-Test-Password-2026';
    $database->beginTransaction();

    try {
        $staffId = $users->createAccount([
            'email' => 'auth-' . $marker . '@example.invalid',
            'display_name' => 'Synthetic Auth Staff',
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'status' => 'active',
            'role_slug' => 'staff',
        ]);
        $validLogin = $auth->authenticate('auth-' . $marker . '@example.invalid', $password, '192.0.2.20');
        expectAuthenticationCheck(
            $validLogin['status'] === 'authenticated'
                && ($validLogin['user']['id'] ?? null) === $staffId
                && ($validLogin['user']['is_admin'] ?? true) === false,
            'correct password authenticates active Academy Staff without admin rights'
        );

        $wrongLogin = $auth->authenticate('auth-' . $marker . '@example.invalid', 'incorrect-password', '192.0.2.21');
        $unknownLogin = $auth->authenticate('missing-' . $marker . '@example.invalid', $password, '192.0.2.22');
        $inactiveId = $users->createAccount([
            'email' => 'inactive-' . $marker . '@example.invalid',
            'display_name' => 'Synthetic Inactive Staff',
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'status' => 'inactive',
            'role_slug' => 'staff',
        ]);
        $inactiveLogin = $auth->authenticate('inactive-' . $marker . '@example.invalid', $password, '192.0.2.23');
        expectAuthenticationCheck(
            $wrongLogin['status'] === 'failed'
                && $unknownLogin['status'] === 'failed'
                && $inactiveLogin['status'] === 'failed',
            'wrong, unknown, and inactive accounts share the same failure outcome'
        );

        $adminId = $users->createAccount([
            'email' => 'admin-' . $marker . '@example.invalid',
            'display_name' => 'Synthetic Admin',
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'status' => 'active',
            'role_slug' => 'admin',
        ]);
        $adminLogin = $auth->authenticate('admin-' . $marker . '@example.invalid', $password, '192.0.2.24');
        expectAuthenticationCheck(
            $adminLogin['status'] === 'authenticated'
                && ($adminLogin['user']['id'] ?? null) === $adminId
                && ($adminLogin['user']['is_admin'] ?? false) === true,
            'correct password authenticates the Administrator role distinctly'
        );
        expectAuthenticationCheck(
            $users->isActiveStaffMember($staffId)
                && !$users->isActiveAdministrator($staffId)
                && $users->isActiveStaffMember($adminId)
                && $users->isActiveAdministrator($adminId)
                && !$users->isActiveStaffMember($inactiveId),
            'active roles grant only their expected access and inactive staff is denied'
        );

        $limitedEmail = 'limited-' . $marker . '@example.invalid';
        $users->createAccount([
            'email' => $limitedEmail,
            'display_name' => 'Synthetic Rate Limited Staff',
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'status' => 'active',
            'role_slug' => 'staff',
        ]);
        for ($attempt = 0; $attempt < 5; $attempt++) {
            expectAuthenticationCheck(
                $auth->authenticate($limitedEmail, 'incorrect-password', '192.0.2.25')['status'] === 'failed',
                'failed attempt remains generic before the rate limit'
            );
        }
        expectAuthenticationCheck(
            $auth->authenticate($limitedEmail, $password, '192.0.2.25')['status'] === 'rate_limited',
            'five failures trigger a cooldown before password verification'
        );

        // Session tests verify rotation, cookie flags, CSRF, idle expiry, and absolute expiry.
        Session::start();
        $oldSessionId = session_id();
        Session::signIn($staffId, 'Synthetic Auth Staff', false);
        $newSessionId = session_id();
        $sessionUser = Session::authenticatedUser();
        $cookie = session_get_cookie_params();
        expectAuthenticationCheck($oldSessionId !== $newSessionId, 'sign-in rotates the session identifier');
        expectAuthenticationCheck(
            is_array($sessionUser) && $sessionUser['id'] === $staffId && $sessionUser['is_admin'] === false,
            'signed-in session returns the verified identity and role flag'
        );
        expectAuthenticationCheck(
            ($cookie['httponly'] ?? false) === true && ($cookie['samesite'] ?? '') === 'Lax',
            'session cookie uses HttpOnly and SameSite=Lax'
        );
        expectAuthenticationCheck(
            ini_get('session.use_strict_mode') === '1'
                && ini_get('session.use_only_cookies') === '1'
                && ini_get('session.use_trans_sid') === '0',
            'session rejects supplied IDs and disables URL-based IDs'
        );
        $csrfToken = Csrf::token();
        expectAuthenticationCheck(Csrf::isValid($csrfToken), 'session CSRF token validates');
        expectAuthenticationCheck(!Csrf::isValid('wrong-token'), 'incorrect CSRF token is rejected');

        $_SESSION['last_activity'] = time() - 1801;
        expectAuthenticationCheck(Session::authenticatedUser() === null, 'idle session expires after its timeout');
        Session::signIn($staffId, 'Synthetic Auth Staff', false);
        $_SESSION['created_at'] = time() - 28801;
        $_SESSION['last_activity'] = time();
        expectAuthenticationCheck(Session::authenticatedUser() === null, 'absolute session lifetime cannot be extended by activity');

        Session::signIn($staffId, 'Synthetic Auth Staff', false);
        Session::destroy();
        expectAuthenticationCheck(Session::authenticatedUser() === null, 'logout removes the authenticated session identity');
        Session::destroy();

        // HTTPS is emulated in-process to check the production cookie flag without a browser login.
        $_SERVER['HTTPS'] = 'on';
        Session::start();
        $httpsCookie = session_get_cookie_params();
        expectAuthenticationCheck(($httpsCookie['secure'] ?? false) === true, 'HTTPS sessions receive the Secure cookie flag');
        Session::destroy();
        unset($_SERVER['HTTPS']);
    } finally {
        if (session_status() === PHP_SESSION_ACTIVE) {
            Session::destroy();
        }
        if ($database->inTransaction()) {
            $database->rollBack();
        }
    }

    fwrite(STDOUT, "Authentication and session security checks passed; synthetic database rows rolled back.\n");
} catch (Throwable $error) {
    fwrite(STDERR, 'Authentication security check failed (' . get_class($error) . ').' . "\n");
    exit(1);
}
