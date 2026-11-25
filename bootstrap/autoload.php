<?php

declare(strict_types=1);

// Prefer Composer's generated loader once Composer is installed. Until then,
// this small fallback maps the project's Cva namespace to the app/ directory.
$projectRoot = dirname(__DIR__);
$composerLoader = $projectRoot . '/vendor/autoload.php';

if (is_file($composerLoader)) {
    require_once $composerLoader;
    return;
}

spl_autoload_register(static function (string $className) use ($projectRoot): void {
    $namespacePrefix = 'Cva\\';

    if (strncmp($className, $namespacePrefix, strlen($namespacePrefix)) !== 0) {
        return;
    }

    $relativeClass = substr($className, strlen($namespacePrefix));
    $classFile = $projectRoot . '/app/'
        . str_replace('\\', '/', $relativeClass)
        . '.php';

    if (is_file($classFile)) {
        require_once $classFile;
    }
});
