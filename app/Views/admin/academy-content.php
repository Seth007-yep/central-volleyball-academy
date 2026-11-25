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
    <title>Academy information | Central Volleyball Academy</title>
    <link rel="stylesheet" href="<?= Html::escape(Url::path('/assets/css/site.css')) ?>">
</head>
<body>
    <a class="skip-link" href="#main-content">Skip to content</a>
    <header class="site-header">
        <div class="container header-content">
            <a class="wordmark" href="<?= Html::escape(Url::path('/admin')) ?>">Central Volleyball Academy</a>
            <form method="post" action="<?= Html::escape(Url::path('/admin/logout')) ?>">
                <input type="hidden" name="csrf_token" value="<?= Html::escape($csrfToken) ?>">
                <button class="button button-secondary button-small" type="submit">Sign out</button>
            </form>
        </div>
    </header>
    <?php require __DIR__ . '/_navigation.php'; ?>
    <main class="container admin-content" id="main-content">
        <p class="eyebrow">Public site content</p>
        <h1>Academy information</h1>
        <p class="admin-welcome">Edit confirmed academy details. The academy name and yellow/blue design colors are configured separately.</p>
        <?php if (is_array($notice)): ?>
            <p class="notice" role="status"><?= Html::escape((string) ($notice['message'] ?? '')) ?></p>
        <?php endif; ?>
        <?php if (isset($errors['form'])): ?>
            <p class="notice notice-error" role="alert"><?= Html::escape($errors['form']) ?></p>
        <?php endif; ?>
        <?php foreach ($errors as $field => $message): ?>
            <?php if ($field !== 'form'): ?>
                <p class="notice notice-error" role="alert"><?= Html::escape($message) ?></p>
            <?php endif; ?>
        <?php endforeach; ?>

        <form class="admin-form" method="post" action="<?= Html::escape(Url::path('/admin/academy')) ?>">
            <input type="hidden" name="csrf_token" value="<?= Html::escape($csrfToken) ?>">
            <div class="field">
                <label class="field-label" for="introduction">Homepage introduction</label>
                <textarea id="introduction" name="introduction" rows="3" maxlength="2000"><?= Html::escape((string) ($content['introduction'] ?? '')) ?></textarea>
            </div>
            <div class="field">
                <label class="field-label" for="about-text">About the academy</label>
                <textarea id="about-text" name="about_text" rows="6" maxlength="6000"><?= Html::escape((string) ($content['about_text'] ?? '')) ?></textarea>
            </div>
            <div class="form-grid">
                <div class="field">
                    <label class="field-label" for="mission">Mission</label>
                    <textarea id="mission" name="mission" rows="4" maxlength="2000"><?= Html::escape((string) ($content['mission'] ?? '')) ?></textarea>
                </div>
                <div class="field">
                    <label class="field-label" for="vision">Vision</label>
                    <textarea id="vision" name="vision" rows="4" maxlength="2000"><?= Html::escape((string) ($content['vision'] ?? '')) ?></textarea>
                </div>
            </div>
            <div class="field">
                <label class="field-label" for="coaching-philosophy">Coaching philosophy</label>
                <textarea id="coaching-philosophy" name="coaching_philosophy" rows="4" maxlength="4000"><?= Html::escape((string) ($content['coaching_philosophy'] ?? '')) ?></textarea>
            </div>
            <fieldset class="form-fieldset">
                <legend>Public contact details</legend>
                <p class="field-help">Leave a field blank until the academy confirms the information.</p>
                <div class="form-grid">
                    <div class="field">
                        <label class="field-label" for="contact-email">Email</label>
                        <input id="contact-email" name="contact_email" type="email" maxlength="254" value="<?= Html::escape((string) ($content['contact_email'] ?? '')) ?>">
                    </div>
                    <div class="field">
                        <label class="field-label" for="contact-phone">Phone</label>
                        <input id="contact-phone" name="contact_phone" type="tel" maxlength="40" value="<?= Html::escape((string) ($content['contact_phone'] ?? '')) ?>">
                    </div>
                </div>
                <div class="field">
                    <label class="field-label" for="contact-location">Location or address</label>
                    <input id="contact-location" name="contact_location" type="text" maxlength="255" value="<?= Html::escape((string) ($content['contact_location'] ?? '')) ?>">
                </div>
            </fieldset>
            <button class="button" type="submit">Save academy information</button>
        </form>
    </main>
</body>
</html>
