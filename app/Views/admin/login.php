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
    <title>Staff sign in | <?= Html::escape($appConfig['name']) ?></title>
    <link rel="stylesheet" href="<?= Html::escape(Url::path('/assets/css/site.css')) ?>">
</head>
<body>
    <a class="skip-link" href="#main-content">Skip to content</a>
    <main class="auth-page" id="main-content">
        <section class="auth-card" aria-labelledby="login-title">
            <a class="wordmark" href="<?= Html::escape(Url::path('/')) ?>"><?= Html::escape($appConfig['name']) ?></a>
            <p class="eyebrow">Staff access</p>
            <h1 id="login-title">Sign in</h1>
            <p class="auth-intro">This area is for authorized academy staff.</p>

            <?php if ($errorMessage !== null): ?>
                <p class="notice notice-danger" role="alert">
                    <?= Html::escape($errorMessage) ?>
                </p>
            <?php endif; ?>

            <form method="post" action="<?= Html::escape(Url::path('/admin/login')) ?>">
                <input type="hidden" name="csrf_token" value="<?= Html::escape($csrfToken) ?>">

                <div class="field">
                    <label class="field-label" for="email">Email address</label>
                    <input
                        id="email"
                        name="email"
                        type="email"
                        value="<?= Html::escape($email) ?>"
                        autocomplete="username"
                        maxlength="254"
                        required
                    >
                </div>

                <div class="field">
                    <label class="field-label" for="password">Password</label>
                    <input
                        id="password"
                        name="password"
                        type="password"
                        autocomplete="current-password"
                        maxlength="72"
                        required
                    >
                </div>

                <div class="form-actions">
                    <button class="button" type="submit">Sign in</button>
                    <a class="text-link" href="<?= Html::escape(Url::path('/')) ?>">Return to the public website</a>
                </div>
            </form>
        </section>
    </main>
</body>
</html>
