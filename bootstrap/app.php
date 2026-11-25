<?php

declare(strict_types=1);

// Keep bootstrap work small and predictable. The front controller is the only
// public entry point, so it loads configuration before choosing a page.
$projectRoot = dirname(__DIR__);
require_once __DIR__ . '/autoload.php';
$appConfig = require $projectRoot . '/config/app.php';

// Show detailed errors only when a developer explicitly enables APP_DEBUG.
// Production logs errors but should never print internals into an HTTP response.
error_reporting(E_ALL);
ini_set('display_errors', $appConfig['debug'] ? '1' : '0');
ini_set('log_errors', '1');

return $appConfig;
