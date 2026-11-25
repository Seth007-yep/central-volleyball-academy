<?php

declare(strict_types=1);

// Migrations run from the CLI with a separate database account that has DDL
// rights. The public application uses the restricted account in database.php.
$databaseSettings = require __DIR__ . '/database.php';
$appEnvironment = getenv('APP_ENV');
$isLocalEnvironment = $appEnvironment === false
    || $appEnvironment === ''
    || strtolower($appEnvironment) === 'local';
$localConfigPath = __DIR__ . '/local.php';
$localConfig = $isLocalEnvironment && is_file($localConfigPath)
    ? require $localConfigPath
    : [];
$localMigrationDatabase = is_array($localConfig)
    && is_array($localConfig['migration_database'] ?? null)
    ? $localConfig['migration_database']
    : [];
$username = getenv('DB_MIGRATION_USERNAME');
$password = getenv('DB_MIGRATION_PASSWORD');

if (($username === false || $username === '') && isset($localMigrationDatabase['username'])) {
    $username = (string) $localMigrationDatabase['username'];
}

if (($password === false || $password === '') && isset($localMigrationDatabase['password'])) {
    $password = (string) $localMigrationDatabase['password'];
}

if (!is_string($username) || $username === '' || !is_string($password)) {
    throw new RuntimeException('Migration database credentials are not configured.');
}

$databaseSettings['username'] = $username;
$databaseSettings['password'] = $password;

return $databaseSettings;
