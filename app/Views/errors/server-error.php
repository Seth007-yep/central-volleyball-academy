<?php

declare(strict_types=1);

use Cva\Support\Html;
use Cva\Support\Url;

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Service unavailable</title>
    <link rel="stylesheet" href="<?= Html::escape(Url::path('/assets/css/site.css')) ?>">
</head>
<body>
    <main class="container section-layout">
        <div class="section-copy">
            <h1>We could not complete that request.</h1>
            <p>Please try again later. No private diagnostic details are shown here.</p>
            <a href="<?= Html::escape(Url::path('/')) ?>">Return to the homepage</a>
        </div>
    </main>
</body>
</html>
