<?php

declare(strict_types=1);

// PHP does not read a .env file by itself. Read values supplied by Apache or
// the hosting environment instead, so configuration can stay outside Git.
$environment = getenv('APP_ENV');
$debugValue = getenv('APP_DEBUG');

return [
    // This is the official academy name confirmed by the project owner.
    'name' => 'Central Volleyball Academy',

    // Local is the safe default: production must be explicitly configured.
    'environment' => $environment !== false ? $environment : 'local',

    // Invalid or missing values resolve to false, avoiding accidental debug
    // output if the production environment is not configured correctly.
    'debug' => filter_var(
        $debugValue !== false ? $debugValue : 'false',
        FILTER_VALIDATE_BOOLEAN
    ),
];
