<?php

declare(strict_types=1);

// The HMAC key lets rate limiting use opaque hashes instead of saving raw
// email addresses or IP addresses. Keep it in the process environment or the
// ignored, local-only config file; never put it in a migration or Git.
$appEnvironment = getenv('APP_ENV');
$isLocalEnvironment = $appEnvironment === false
    || $appEnvironment === ''
    || strtolower($appEnvironment) === 'local';
$localConfigPath = __DIR__ . '/local.php';
$localConfig = $isLocalEnvironment && is_file($localConfigPath)
    ? require $localConfigPath
    : [];
$localKey = is_array($localConfig) ? ($localConfig['app_key'] ?? null) : null;
$appKey = getenv('APP_KEY');

if (($appKey === false || $appKey === '') && is_string($localKey)) {
    $appKey = $localKey;
}

if (!is_string($appKey) || strlen($appKey) < 64) {
    throw new RuntimeException('APP_KEY must contain at least 64 characters.');
}

return ['app_key' => $appKey];
