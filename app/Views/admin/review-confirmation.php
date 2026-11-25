<?php

declare(strict_types=1);

use Cva\Support\Html;
use Cva\Support\Url;

$decision = (string) $pendingReview['status'];
$decisionLabels = [
    'pending' => 'Pending',
    'under_review' => 'Under review',
    'approved' => 'Approve application',
    'rejected' => 'Reject application',
    'needs_information' => 'Request more information',
    'cancelled' => 'Cancel application',
];
$decisionLabel = $decisionLabels[$decision] ?? 'Change application status';
$applicantName = trim((string) $registration['first_name'] . ' ' . (string) $registration['last_name']);
$confirmedNotes = is_string($pendingReview['notes'] ?? null) && trim($pendingReview['notes']) !== ''
    ? $pendingReview['notes']
    : (trim((string) ($registration['admin_notes'] ?? '')) !== ''
        ? 'Clear the existing private staff notes.'
        : 'No private staff notes.');

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Confirm application decision | Central Volleyball Academy</title>
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
        <p class="eyebrow">Private applicant details</p>
        <h1>Confirm application decision</h1>
        <section class="card detail-card" aria-labelledby="confirmation-title">
            <h2 id="confirmation-title"><?= Html::escape($decisionLabel) ?>?</h2>
            <dl class="detail-list">
                <dt>Applicant</dt><dd><?= Html::escape($applicantName) ?></dd>
                <dt>Program interest</dt><dd><?= Html::escape((string) ($registration['program_interest'] ?? 'Not provided')) ?></dd>
                <dt>Current status</dt><dd><?= Html::escape($statusLabels[$registration['status']] ?? (string) $registration['status']) ?></dd>
                <dt>Selected decision</dt><dd><?= Html::escape($decisionLabels[$decision] ?? $decision) ?></dd>
                <dt>Private staff notes</dt><dd class="preserve-lines"><?= Html::escape($confirmedNotes) ?></dd>
            </dl>
            <?php if ($decision === 'approved'): ?>
                <p class="notice">Confirming approval also creates or updates the private player record for this application.</p>
            <?php else: ?>
                <p class="field-help">This decision will be recorded in the application history and audit log.</p>
            <?php endif; ?>
            <div class="admin-filter-actions">
                <form method="post" action="<?= Html::escape(Url::path('/admin/registrations/review/confirm')) ?>">
                    <input type="hidden" name="csrf_token" value="<?= Html::escape($csrfToken) ?>">
                    <input type="hidden" name="confirmation_token" value="<?= Html::escape($pendingReview['token']) ?>">
                    <button class="button" type="submit">Confirm: <?= Html::escape($decisionLabels[$decision] ?? $decision) ?></button>
                </form>
                <form method="post" action="<?= Html::escape(Url::path('/admin/registrations/review/cancel')) ?>">
                    <input type="hidden" name="csrf_token" value="<?= Html::escape($csrfToken) ?>">
                    <input type="hidden" name="confirmation_token" value="<?= Html::escape($pendingReview['token']) ?>">
                    <button class="button button-secondary" type="submit">Cancel and return</button>
                </form>
            </div>
        </section>
    </main>
</body>
</html>
