<?php

declare(strict_types=1);

// Process environment values take priority. On a local development machine,
// config/local.php may supply private values; .gitignore keeps it out of Git,
// and it lives outside public/ so Apache cannot serve it directly.
$appEnvironment = getenv('APP_ENV');
$isLocalEnvironment = $appEnvironment === false
    || $appEnvironment === ''
    || strtolower($appEnvironment) === 'local';
$localConfigPath = __DIR__ . '/local.php';
$localConfig = $isLocalEnvironment && is_file($localConfigPath)
    ? require $localConfigPath
    : [];
$localDatabase = is_array($localConfig) && is_array($localConfig['database'] ?? null)
    ? $localConfig['database']
    : [];

$requiredSetting = static function (string $name, string $localKey) use ($localDatabase): string {
    $value = getenv($name);

    if (($value === false || $value === '') && isset($localDatabase[$localKey])) {
        $value = (string) $localDatabase[$localKey];
    }

    if ($value === false || $value === '') {
        // Name the missing setting, but never include credential values in an error.
        throw new RuntimeException('Required database setting is missing: ' . $name);
    }

    return $value;
};

$host = $requiredSetting('DB_HOST', 'host');
$databaseName = $requiredSetting('DB_DATABASE', 'database');
$rawPort = getenv('DB_PORT');
if (($rawPort === false || $rawPort === '') && isset($localDatabase['port'])) {
    $rawPort = (string) $localDatabase['port'];
}
$port = filter_var(
    $rawPort !== false && $rawPort !== '' ? $rawPort : '3306',
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1, 'max_range' => 65535]]
);

// DSN delimiters are intentionally rejected so environment values cannot
// append arbitrary PDO connection options.
if (!preg_match('/\A[a-zA-Z0-9._:-]+\z/', $host)) {
    throw new RuntimeException('DB_HOST contains unsupported characters.');
}

if (!preg_match('/\A[a-zA-Z0-9_]+\z/', $databaseName)) {
    throw new RuntimeException('DB_DATABASE contains unsupported characters.');
}

if ($port === false) {
    throw new RuntimeException('DB_PORT must be an integer from 1 to 65535.');
}

return [
    'host' => $host,
    'port' => $port,
    'database' => $databaseName,
    'username' => $requiredSetting('DB_USERNAME', 'username'),
    'password' => $requiredSetting('DB_PASSWORD', 'password'),
];
