<?php

declare(strict_types=1);

namespace Cva\Support;

/** Centralize secure session startup, expiry, login, and logout behavior. */
final class Session
{
    private const IDLE_TIMEOUT_SECONDS = 1800;
    private const ABSOLUTE_TIMEOUT_SECONDS = 28800;

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        // Strict mode rejects attacker-supplied session IDs; cookie-only mode
        // prevents PHP from putting session IDs into URLs.
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');

        // Store local sessions in private project storage instead of XAMPP's
        // shared temp directory, whose permissions can differ by server user.
        $configuredPath = getenv('SESSION_SAVE_PATH');
        $sessionPath = $configuredPath !== false && $configuredPath !== ''
            ? $configuredPath
            : dirname(__DIR__, 2) . '/storage/sessions';

        if (!is_dir($sessionPath) || !is_writable($sessionPath)) {
            throw new \RuntimeException('The private session storage directory is unavailable.');
        }

        session_save_path($sessionPath);
        session_name('cva_session');

        $httpsIsActive = isset($_SERVER['HTTPS'])
            && $_SERVER['HTTPS'] !== ''
            && strtolower((string) $_SERVER['HTTPS']) !== 'off';
        $appEnvironment = getenv('APP_ENV');
        $isProduction = is_string($appEnvironment)
            && strtolower($appEnvironment) === 'production';

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            // Force Secure in production even if the web server is misconfigured;
            // the host must redirect HTTP to HTTPS for sign-in to work.
            'secure' => $httpsIsActive || $isProduction,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();
    }

    /** Return the current account after enforcing inactivity and absolute limits. */
    public static function authenticatedUser(): ?array
    {
        self::start();

        if (!isset($_SESSION['user_id'], $_SESSION['display_name'])) {
            return null;
        }

        $now = time();
        $createdAt = (int) ($_SESSION['created_at'] ?? 0);
        $lastActivity = (int) ($_SESSION['last_activity'] ?? 0);

        if (
            $createdAt <= 0
            || $lastActivity <= 0
            || $now - $lastActivity > self::IDLE_TIMEOUT_SECONDS
            || $now - $createdAt > self::ABSOLUTE_TIMEOUT_SECONDS
        ) {
            self::destroy();
            return null;
        }

        // Touch only the activity time; the absolute lifetime cannot slide.
        $_SESSION['last_activity'] = $now;

        return [
            'id' => (int) $_SESSION['user_id'],
            'display_name' => (string) $_SESSION['display_name'],
            'is_admin' => (bool) ($_SESSION['is_admin'] ?? false),
        ];
    }

    /** Refresh the display-only role flag after the middleware checks the database. */
    public static function setAdministratorFlag(bool $isAdministrator): void
    {
        self::start();
        $_SESSION['is_admin'] = $isAdministrator;
    }

    /** Replace the session ID after the credentials have been verified. */
    public static function signIn(int $userId, string $displayName, bool $isAdministrator): void
    {
        self::start();
        session_regenerate_id(true);

        $_SESSION = [
            'user_id' => $userId,
            'display_name' => $displayName,
            'is_admin' => $isAdministrator,
            'created_at' => time(),
            'last_activity' => time(),
        ];
    }

    /** Clear server-side session data and expire the browser cookie. */
    public static function destroy(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            self::start();
        }

        $_SESSION = [];
        $cookie = session_get_cookie_params();

        if (ini_get('session.use_cookies')) {
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $cookie['path'] ?? '/',
                'domain' => $cookie['domain'] ?? '',
                'secure' => (bool) ($cookie['secure'] ?? false),
                'httponly' => (bool) ($cookie['httponly'] ?? true),
                'samesite' => $cookie['samesite'] ?? 'Lax',
            ]);
        }

        session_destroy();
    }
}
