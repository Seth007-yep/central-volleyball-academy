<?php

declare(strict_types=1);

namespace Cva\Support;

/** Build application URLs that also work when Apache mounts the app in a subfolder. */
final class Url
{
    /** Return the configured mount path, such as /central_volleyball_academy. */
    public static function basePath(): string
    {
        $configuredPath = getenv('APP_BASE_PATH');
        if (is_string($configuredPath) && $configuredPath !== '') {
            $normalizedPath = '/' . trim(str_replace('\\', '/', $configuredPath), '/');
            return $normalizedPath === '/' ? '' : $normalizedPath;
        }

        // At the web root, index.php lives directly under `/`; dirname then
        // yields `/` and the site keeps using ordinary root-relative links.
        $scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php');
        $scriptPath = parse_url($scriptName, PHP_URL_PATH);
        $basePath = dirname(is_string($scriptPath) ? $scriptPath : '/index.php');

        return $basePath === '/' || $basePath === '.' ? '' : rtrim($basePath, '/');
    }

    /** Prefix an application path while preserving its leading slash. */
    public static function path(string $path): string
    {
        $normalizedPath = '/' . ltrim($path, '/');

        return self::basePath() . $normalizedPath;
    }

    /** Remove the configured mount path before matching an application route. */
    public static function stripBasePath(string $path): string
    {
        $basePath = self::basePath();
        if (
            $basePath === ''
            || ($path !== $basePath && !str_starts_with($path, $basePath . '/'))
        ) {
            return $path;
        }

        $applicationPath = substr($path, strlen($basePath));

        return $applicationPath === '' ? '/' : $applicationPath;
    }
}
