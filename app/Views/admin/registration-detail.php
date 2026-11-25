<?php

declare(strict_types=1);

use Cva\Support\Html;
use Cva\Support\Url;

$statusLabels = [
    'pending' => 'Pending',
    'under_review' => 'Under review',
    'approved' => 'Approved',
    'rejected' => 'Rejected',
    'needs_information' => 'Needs information',
    'cancelled' => 'Cancelled',
];
$showValue = static fn (string $key): string => Html::escape((string) ($registration[$key] ?? 'Not provided'));

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Review application | Central Volleyball Academy</title>
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
        <p class="eyebrow">Private applicant details</p>
        <h1>Review application</h1>
        <p><a href="<?= Html::escape(Url::path('/admin/registrations')) ?>">Back to registrations</a></p>
        <?php if (is_array($notice)): ?><p class="notice" role="status"><?= Html::escape((string) ($notice['message'] ?? '')) ?></p><?php endif; ?>

        <section class="card detail-card" aria-labelledby="applicant-title">
            <h2 id="applicant-title">Applicant</h2>
            <dl class="detail-list">
                <dt>Name</dt><dd><?= $showValue('first_name') ?> <?= $showValue('last_name') ?></dd>
                <dt>Date of birth</dt><dd><?= $showValue('date_of_birth') ?></dd>
                <dt>Email</dt><dd><?= $showValue('email') ?></dd>
                <dt>Phone</dt><dd><?= $showValue('phone') ?></dd>
                <dt>Program interest</dt><dd><?= $showValue('program_interest') ?></dd>
                <dt>Previous experience</dt><dd class="preserve-lines"><?= $showValue('previous_experience') ?></dd>
                <dt>Application received</dt><dd><?= $showValue('created_at') ?></dd>
            </dl>
            <h2>Parent or guardian</h2>
            <dl class="detail-list">
                <dt>Name</dt><dd><?= $showValue('guardian_name') ?></dd>
                <dt>Relationship</dt><dd><?= $showValue('guardian_relationship') ?></dd>
                <dt>Email</dt><dd><?= $showValue('guardian_email') ?></dd>
                <dt>Phone</dt><dd><?= $showValue('guardian_phone') ?></dd>
            </dl>
        </section>

        <form class="admin-form" method="post" action="<?= Html::escape(Url::path('/admin/registrations/review')) ?>">
            <h2>Review decision</h2>
            <input type="hidden" name="csrf_token" value="<?= Html::escape($csrfToken) ?>">
            <input type="hidden" name="registration_id" value="<?= (int) $registration['id'] ?>">
            <div class="field">
                <label class="field-label" for="review-status">Status</label>
                <select id="review-status" name="status">
                    <?php foreach ($statusLabels as $value => $label): ?>
                        <option value="<?= Html::escape($value) ?>"<?= $registration['status'] === $value ? ' selected' : '' ?>><?= Html::escape($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label class="field-label" for="admin-notes">Private staff notes</label>
                <textarea id="admin-notes" name="admin_notes" rows="4" maxlength="2000"><?= Html::escape((string) ($registration['admin_notes'] ?? '')) ?></textarea>
                <p class="field-help">Keep notes factual and necessary. Notes are visible to authorized staff.</p>
            </div>
            <p class="field-help">Selecting a status opens a separate confirmation screen. No decision is saved until you confirm it there.</p>
            <button class="button" type="submit">Continue to decision confirmation</button>
        </form>
    </main>
</body>
</html>
