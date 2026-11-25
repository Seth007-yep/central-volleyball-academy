<?php

declare(strict_types=1);

// Apache's document root points here; application source and configuration
// stay above public/ and cannot be requested directly by a browser.
$projectRoot = dirname(__DIR__);
$appConfig = require $projectRoot . '/bootstrap/app.php';
$routes = require $projectRoot . '/config/routes.php';

header('Content-Type: text/html; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Frame-Options: DENY');
header('Permissions-Policy: camera=(), geolocation=(), microphone=()');

// All current assets and form actions are same-origin; add external sources
// only when a confirmed feature actually needs them and the policy is reviewed.
header(
    "Content-Security-Policy: default-src 'self'; base-uri 'self'; form-action 'self'; "
    . "frame-ancestors 'none'; object-src 'none'; img-src 'self'; media-src 'self'; "
    . "style-src 'self'; script-src 'self'"
);

$isProduction = strtolower((string) $appConfig['environment']) === 'production';
$isHttpsRequest = isset($_SERVER['HTTPS'])
    && $_SERVER['HTTPS'] !== ''
    && strtolower((string) $_SERVER['HTTPS']) !== 'off';

// HSTS is sent only after HTTPS is active; never advertise it over local HTTP.
if ($isProduction && $isHttpsRequest) {
    header('Strict-Transport-Security: max-age=31536000');
}

// Normalize only the path; query-string values never select a PHP include.
$parsedPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$requestPath = is_string($parsedPath) && $parsedPath !== '' ? $parsedPath : '/';
$requestPath = '/' . trim($requestPath, '/');
$requestPath = $requestPath === '/' ? '/' : rtrim($requestPath, '/');
$requestPath = \Cva\Support\Url::stripBasePath($requestPath);
$requestMethod = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$routeKey = $requestMethod . ' ' . $requestPath;
$route = $routes[$routeKey] ?? null;

if (!is_array($route)) {
    $allowedMethods = [];

    foreach (array_keys($routes) as $definedRoute) {
        [$definedMethod, $definedPath] = explode(' ', $definedRoute, 2);
        if ($definedPath === $requestPath) {
            $allowedMethods[] = $definedMethod;
        }
    }

    if ($allowedMethods !== []) {
        http_response_code(405);
        header('Allow: ' . implode(', ', $allowedMethods));
        require $projectRoot . '/app/Views/errors/method-not-allowed.php';
        exit;
    }

    http_response_code(404);
    require $projectRoot . '/app/Views/errors/not-found.php';
    exit;
}

try {
    if ($route['admin'] && !\Cva\Middleware\AdminAuthMiddleware::authorize($route['access'] ?? 'staff')) {
        exit;
    }

    if (isset($route['view'])) {
        require $route['view'];
        exit;
    }

    $controllerClass = $route['controller'];
    $action = $route['action'];
    (new $controllerClass())->$action();
} catch (Throwable $error) {
    // Keep database internals and request data out of the HTTP response/log line.
    error_log('Unhandled application exception (' . get_class($error) . ').');
    http_response_code(500);
    require $projectRoot . '/app/Views/errors/server-error.php';
}
