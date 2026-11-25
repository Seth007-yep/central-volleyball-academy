<?php

declare(strict_types=1);

use Cva\Support\Html;
use Cva\Support\Url;

$display = static function (string $key) use ($member): string {
    $value = $member[$key] ?? '';
    return Html::escape(is_string($value) ? $value : '');
};
$statusLabels = ['active' => 'Active', 'inactive' => 'Inactive', 'suspended' => 'Suspended'];

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit player | Central Volleyball Academy</title>
    <link rel="stylesheet" href="<?= Html::escape(Url::path('/assets/css/site.css')) ?>">
</head>
<body>
    <a class="skip-link" href="#main-content">Skip to content</a>
    <header class="site-header">
        <div class="container header-content">
            <a class="wordmark" href="<?= Html::escape(Url::path('/admin')) ?>">Central Volleyball Academy</a>
            <form method="post" action="<?= Html::escape(Url::path('/admin/logout')) ?>">
                <input type="hidden" name="csrf_token" value="<?= Html::escape(\Cva\Support\Csrf::token()) ?>">
                <button class="button button-secondary button-small" type="submit">Sign out</button>
            </form>
        </div>
    </header>
    <?php require __DIR__ . '/_navigation.php'; ?>
    <main class="container admin-content" id="main-content">
        <p class="eyebrow">Private player record</p>
        <h1>Edit player</h1>
        <p><a href="<?= Html::escape(Url::path('/admin/players')) ?>">Back to players</a></p>
        <?php if (is_array($notice)): ?><p class="notice" role="status"><?= Html::escape((string) ($notice['message'] ?? '')) ?></p><?php endif; ?>
        <?php foreach ($errors as $message): ?><p class="notice notice-error" role="alert"><?= Html::escape($message) ?></p><?php endforeach; ?>
        <form class="admin-form" method="post" action="<?= Html::escape(Url::path('/admin/players/update')) ?>">
            <input type="hidden" name="csrf_token" value="<?= Html::escape($csrfToken) ?>">
            <input type="hidden" name="member_id" value="<?= (int) $member['id'] ?>">
            <div class="form-grid">
                <div class="field"><label class="field-label" for="first-name">First name</label><input id="first-name" name="first_name" maxlength="80" required value="<?= $display('first_name') ?>"></div>
                <div class="field"><label class="field-label" for="last-name">Last name</label><input id="last-name" name="last_name" maxlength="80" required value="<?= $display('last_name') ?>"></div>
            </div>
            <div class="form-grid">
                <div class="field"><label class="field-label" for="date-of-birth">Date of birth</label><input id="date-of-birth" name="date_of_birth" type="date" required value="<?= $display('date_of_birth') ?>"></div>
                <div class="field"><label class="field-label" for="program-interest">Program or training</label><input id="program-interest" name="program_interest" maxlength="120" required value="<?= $display('program_interest') ?>"></div>
            </div>
            <div class="form-grid">
                <div class="field"><label class="field-label" for="email">Email</label><input id="email" name="email" type="email" maxlength="254" value="<?= $display('email') ?>"></div>
                <div class="field"><label class="field-label" for="phone">Phone</label><input id="phone" name="phone" type="tel" maxlength="40" value="<?= $display('phone') ?>"></div>
            </div>
            <div class="field"><label class="field-label" for="experience">Previous volleyball experience</label><textarea id="experience" name="previous_experience" maxlength="2000" rows="3"><?= $display('previous_experience') ?></textarea></div>
            <fieldset class="form-fieldset">
                <legend>Parent or guardian contact</legend>
                <p class="field-help">Required for a player below the configured adult age. Adult-player guardian details are cleared when the record is saved.</p>
                <div class="form-grid">
                    <div class="field"><label class="field-label" for="guardian-name">Name</label><input id="guardian-name" name="guardian_name" maxlength="160" value="<?= $display('guardian_name') ?>"></div>
                    <div class="field"><label class="field-label" for="guardian-relationship">Relationship</label><input id="guardian-relationship" name="guardian_relationship" maxlength="60" value="<?= $display('guardian_relationship') ?>"></div>
                </div>
                <div class="form-grid">
                    <div class="field"><label class="field-label" for="guardian-email">Email</label><input id="guardian-email" name="guardian_email" type="email" maxlength="254" value="<?= $display('guardian_email') ?>"></div>
                    <div class="field"><label class="field-label" for="guardian-phone">Phone</label><input id="guardian-phone" name="guardian_phone" type="tel" maxlength="40" value="<?= $display('guardian_phone') ?>"></div>
                </div>
            </fieldset>
            <div class="field">
                <label class="field-label" for="player-status">Player status</label>
                <select id="player-status" name="status">
                    <?php foreach ($statusLabels as $value => $label): ?><option value="<?= Html::escape($value) ?>"<?= ($member['status'] ?? '') === $value ? ' selected' : '' ?>><?= Html::escape($label) ?></option><?php endforeach; ?>
                </select>
            </div>
            <button class="button" type="submit">Save player record</button>
        </form>
    </main>
</body>
</html>
