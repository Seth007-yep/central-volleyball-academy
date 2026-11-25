<?php

declare(strict_types=1);

use Cva\Support\Html;
use Cva\Support\Url;

$pageTitle = 'Page not found | ' . $appConfig['name'];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= Html::escape($pageTitle) ?></title>
    <link rel="stylesheet" href="<?= Html::escape(Url::path('/assets/css/site.css')) ?>">
</head>
<body>
    <main class="page-shell">
        <section class="setup-panel" aria-labelledby="page-heading">
            <p class="eyebrow">404</p>
            <h1 id="page-heading">Page not found</h1>
            <p class="setup-copy">That address does not match a page in this starter application.</p>
            <a class="text-link" href="<?= Html::escape(Url::path('/')) ?>">Return to the setup page</a>
        </section>
    </main>
</body>
</html>
