<?php

declare(strict_types=1);

namespace Cva\Support;

use PDO;

/** Create the application's shared PDO connection with safe defaults. */
final class Database
{
    private static ?PDO $connection = null;

    /**
     * Open a MySQL/MariaDB connection from config/database.php settings.
     *
     * Call this only inside a request or command that needs database access;
     * public pages with static content should not connect unnecessarily.
     *
     * @param array{host:string,port:int,database:string,username:string,password:string} $settings
     */
    public static function connect(array $settings): PDO
    {
        // Reuse one connection during the current request so middleware and
        // controllers share a transaction/session with the database.
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $settings['host'],
            $settings['port'],
            $settings['database']
        );

        self::$connection = new PDO(
            $dsn,
            $settings['username'],
            $settings['password'],
            [
                // Let callers handle failures without checking false return values.
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,

                // Use a predictable result shape for database-backed code.
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

                // Ask MySQL/MariaDB to prepare statements server-side. Queries
                // must still bind values; this is not a substitute for that.
                PDO::ATTR_EMULATE_PREPARES => false,

                // Avoid a request waiting indefinitely if the DB is unreachable.
                PDO::ATTR_TIMEOUT => 5,
            ]
        );

        return self::$connection;
    }
}
