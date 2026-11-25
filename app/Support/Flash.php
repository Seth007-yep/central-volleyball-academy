<?php

declare(strict_types=1);

namespace Cva\Support;

/** Keep one-request messages out of query strings and form fields. */
final class Flash
{
    public static function add(string $kind, string $message): void
    {
        Session::start();
        $_SESSION['flash_message'] = ['kind' => $kind, 'message' => $message];
    }

    public static function pull(): ?array
    {
        Session::start();
        $message = $_SESSION['flash_message'] ?? null;
        unset($_SESSION['flash_message']);

        return is_array($message) ? $message : null;
    }
}
