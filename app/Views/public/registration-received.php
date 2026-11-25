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
    <title>Application received | Central Volleyball Academy</title>
    <link rel="stylesheet" href="<?= Html::escape(Url::path('/assets/css/site.css')) ?>">
</head>
<body>
    <main class="container form-page" id="main-content">
        <p class="eyebrow">Registration</p>
        <h1>Application received</h1>
        <p role="status"><?= Html::escape((string) ($message['message'] ?? 'Your application was received.')) ?></p>
        <p>Sending an application does not mean a player has been accepted or assigned to a program.</p>
        <a class="button" href="<?= Html::escape(Url::path('/')) ?>">Return to the academy website</a>
    </main>
</body>
</html>
