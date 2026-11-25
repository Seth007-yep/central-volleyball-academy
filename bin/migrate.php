<?php

declare(strict_types=1);

// This script is CLI-only. Database migrations contain trusted project SQL;
// they must never be callable from a web request.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectRoot = dirname(__DIR__);
$databaseSettings = require $projectRoot . '/config/migration.php';
$migrationDirectory = $projectRoot . '/database/migrations';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $database = new mysqli(
        $databaseSettings['host'],
        $databaseSettings['username'],
        $databaseSettings['password'],
        $databaseSettings['database'],
        $databaseSettings['port']
    );
    $database->set_charset('utf8mb4');

    // This table is runner metadata, not an application feature table.
    $database->query(
        'CREATE TABLE IF NOT EXISTS schema_migrations (
            version VARCHAR(190) NOT NULL,
            applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (version)
        ) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
    );

    $migrationFiles = glob($migrationDirectory . '/*.sql') ?: [];
    sort($migrationFiles, SORT_STRING);
    $knownVersions = [];

    foreach ($migrationFiles as $migrationFile) {
        $knownVersions[basename($migrationFile, '.sql')] = $migrationFile;
    }

    // The existing local database was initialized before the runner existed.
    // Baseline it once after checking that the first migration's tables exist.
    if (isset($argv[1]) && $argv[1] === '--baseline=001_create_staff_identity') {
        $baselineTables = ['roles', 'users', 'user_roles', 'audit_logs'];

        foreach ($baselineTables as $tableName) {
            $check = $database->query("SHOW TABLES LIKE '" . $tableName . "'");

            if ($check->num_rows !== 1) {
                throw new RuntimeException('Cannot baseline: an expected staff table is missing.');
            }
        }

        if (!isset($knownVersions['001_create_staff_identity'])) {
            throw new RuntimeException('The requested baseline migration file was not found.');
        }

        $baseline = $database->prepare(
            'INSERT IGNORE INTO schema_migrations (version) VALUES (?)'
        );
        $version = '001_create_staff_identity';
        $baseline->bind_param('s', $version);
        $baseline->execute();
        fwrite(STDOUT, "Recorded existing migration: {$version}\n");
        exit(0);
    }

    $applied = [];
    $result = $database->query('SELECT version FROM schema_migrations');

    while ($row = $result->fetch_assoc()) {
        $applied[$row['version']] = true;
    }

    foreach ($knownVersions as $version => $migrationFile) {
        if (isset($applied[$version])) {
            fwrite(STDOUT, "Already applied: {$version}\n");
            continue;
        }

        $sql = file_get_contents($migrationFile);

        if (!is_string($sql) || trim($sql) === '') {
            throw new RuntimeException('Migration file is empty or unreadable.');
        }

        // DDL commits implicitly in MySQL/MariaDB, so a failed migration can
        // need manual repair before it is run again. Record only on full success.
        $database->multi_query($sql);

        do {
            $statementResult = $database->store_result();
            if ($statementResult !== false) {
                $statementResult->free();
            }
        } while ($database->more_results() && $database->next_result());

        $record = $database->prepare(
            'INSERT INTO schema_migrations (version) VALUES (?)'
        );
        $record->bind_param('s', $version);
        $record->execute();

        fwrite(STDOUT, "Applied: {$version}\n");
    }
} catch (Throwable $error) {
    // Never print connection settings or SQL contents in a terminal log.
    fwrite(STDERR, 'Migration failed (' . get_class($error) . '). Check the database and migration file. ' . $error->getMessage() . "\n");
    exit(1);
}
