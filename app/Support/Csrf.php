<?php

declare(strict_types=1);

namespace Cva\Support;

/** Issue and validate a session-bound token for state-changing requests. */
final class Csrf
{
    public static function token(): string
    {
        Session::start();

        if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    public static function isValid($submittedToken): bool
    {
        Session::start();
        $sessionToken = $_SESSION['csrf_token'] ?? null;

        return is_string($submittedToken)
            && is_string($sessionToken)
            && $sessionToken !== ''
            && hash_equals($sessionToken, $submittedToken);
    }
}
