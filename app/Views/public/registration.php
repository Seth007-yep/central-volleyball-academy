<?php

declare(strict_types=1);

use Cva\Support\Html;
use Cva\Support\Url;

$oldValue = static function (string $key) use ($registrationValues): string {
    $value = $registrationValues[$key] ?? '';
    return Html::escape(is_string($value) ? $value : '');
};
$errorLabels = [
    'first_name' => 'First name', 'last_name' => 'Last name', 'date_of_birth' => 'Date of birth',
    'email' => 'Email', 'phone' => 'Phone', 'program_interest' => 'Program interest',
    'previous_experience' => 'Previous experience', 'guardian_name' => 'Parent or guardian name',
    'guardian_relationship' => 'Relationship', 'guardian_email' => 'Parent or guardian email',
    'guardian_phone' => 'Parent or guardian phone', 'contact_consent' => 'Confirmation',
];

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Player registration | Central Volleyball Academy</title>
    <link rel="stylesheet" href="<?= Html::escape(Url::path('/assets/css/site.css')) ?>">
</head>
<body>
    <a class="skip-link" href="#main-content">Skip to content</a>
    <header class="site-header">
        <div class="container header-content">
            <a class="wordmark" href="<?= Html::escape(Url::path('/')) ?>">Central Volleyball Academy</a>
            <a class="button button-secondary button-small" href="<?= Html::escape(Url::path('/')) ?>">Home</a>
        </div>
    </header>
    <main class="container form-page" id="main-content">
        <p class="eyebrow">New player application</p>
        <h1>Register with the academy</h1>
        <p>Complete this application and academy staff will review it before creating a player record.</p>
        <p class="notice notice-warning" role="note">Development site: do not enter real personal information until the academy approves its privacy notice and registration policy.</p>

        <?php if ($errors !== []): ?>
            <div class="notice notice-error" role="alert">
                <p>Please correct the following before submitting:</p>
                <ul>
                    <?php foreach ($errors as $field => $message): ?>
                        <li><?= Html::escape($errorLabels[$field] ?? 'Form') ?>: <?= Html::escape($message) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form class="public-form" method="post" action="<?= Html::escape(Url::path('/registration')) ?>">
            <input type="hidden" name="csrf_token" value="<?= Html::escape($csrfToken) ?>">
            <fieldset class="form-fieldset">
                <legend>Applicant</legend>
                <div class="form-grid">
                    <div class="field"><label class="field-label" for="first-name">First name</label><input id="first-name" name="first_name" autocomplete="given-name" maxlength="80" required value="<?= $oldValue('first_name') ?>"></div>
                    <div class="field"><label class="field-label" for="last-name">Last name</label><input id="last-name" name="last_name" autocomplete="family-name" maxlength="80" required value="<?= $oldValue('last_name') ?>"></div>
                </div>
                <div class="form-grid">
                    <div class="field"><label class="field-label" for="date-of-birth">Date of birth</label><input id="date-of-birth" name="date_of_birth" type="date" autocomplete="bday" required value="<?= $oldValue('date_of_birth') ?>"></div>
                    <div class="field"><label class="field-label" for="program-interest">Program or training of interest</label><input id="program-interest" name="program_interest" maxlength="120" required value="<?= $oldValue('program_interest') ?>"><p class="field-help">Enter the team, age group, or type of training if known.</p></div>
                </div>
                <div class="form-grid">
                    <div class="field"><label class="field-label" for="email">Applicant email (adults)</label><input id="email" name="email" type="email" autocomplete="email" maxlength="254" value="<?= $oldValue('email') ?>"></div>
                    <div class="field"><label class="field-label" for="phone">Applicant phone (adults)</label><input id="phone" name="phone" type="tel" autocomplete="tel" maxlength="40" value="<?= $oldValue('phone') ?>"></div>
                </div>
                <div class="field"><label class="field-label" for="experience">Previous volleyball experience (optional)</label><textarea id="experience" name="previous_experience" maxlength="2000" rows="3"><?= $oldValue('previous_experience') ?></textarea></div>
            </fieldset>

            <fieldset class="form-fieldset">
                <legend>Parent or guardian</legend>
                <p class="field-help">Required for applicants under <?= (int) $adultAge ?>. An adult applicant can leave these fields blank.</p>
                <div class="form-grid">
                    <div class="field"><label class="field-label" for="guardian-name">Name</label><input id="guardian-name" name="guardian_name" autocomplete="name" maxlength="160" value="<?= $oldValue('guardian_name') ?>"></div>
                    <div class="field"><label class="field-label" for="guardian-relationship">Relationship to applicant</label><input id="guardian-relationship" name="guardian_relationship" maxlength="60" value="<?= $oldValue('guardian_relationship') ?>"></div>
                </div>
                <div class="form-grid">
                    <div class="field"><label class="field-label" for="guardian-email">Email</label><input id="guardian-email" name="guardian_email" type="email" autocomplete="email" maxlength="254" value="<?= $oldValue('guardian_email') ?>"></div>
                    <div class="field"><label class="field-label" for="guardian-phone">Phone</label><input id="guardian-phone" name="guardian_phone" type="tel" autocomplete="tel" maxlength="40" value="<?= $oldValue('guardian_phone') ?>"></div>
                </div>
            </fieldset>

            <label class="checkbox-field"><input type="checkbox" name="contact_consent" value="1" required<?= (isset($registrationValues['contact_consent']) && $registrationValues['contact_consent'] === '1') ? ' checked' : '' ?>> I confirm that I am the applicant or the applicant's parent/guardian, am authorized to submit these details, and agree that the academy may contact me about this application.</label>
            <button class="button" type="submit">Send application</button>
        </form>
    </main>
</body>
</html>
