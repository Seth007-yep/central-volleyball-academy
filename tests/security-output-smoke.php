<?php

declare(strict_types=1);

// Exercise the shared HTML output encoder with hostile text and malformed UTF-8.
// Run with: C:\xampp\php\php.exe tests\security-output-smoke.php
$projectRoot = dirname(__DIR__);
require $projectRoot . '/bootstrap/autoload.php';

use Cva\Support\Html;

function expectOutputSecurityCheck(bool $condition, string $checkName): void
{
    if (!$condition) {
        throw new RuntimeException('Failed check: ' . $checkName);
    }
}

$hostileText = '"><script>alert(\'x\')</script> &';
$escaped = Html::escape($hostileText);
expectOutputSecurityCheck(
    !str_contains($escaped, '<script') && str_contains($escaped, '&lt;script&gt;'),
    'script markup is rendered as text'
);
expectOutputSecurityCheck(
    str_contains($escaped, '&quot;') && str_contains($escaped, '&#039;') && str_contains($escaped, '&amp;'),
    'quotes and ampersands are encoded for HTML text and attribute contexts'
);
expectOutputSecurityCheck(
    Html::escape("invalid-\xC3-byte") === 'invalid-�-byte',
    'malformed UTF-8 is replaced without breaking the response'
);

fwrite(STDOUT, "HTML output encoding checks passed (3 checks).\n");
