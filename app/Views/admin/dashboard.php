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
    <title>Admin | Central Volleyball Academy</title>
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
        <p class="eyebrow">Administration</p>
        <h1>Dashboard</h1>
        <p class="admin-welcome">Welcome, <?= Html::escape($user['display_name'] ?? 'staff member') ?></p>

        <section class="dashboard-summary" aria-label="Academy overview">
            <article class="card summary-card">
                <h2>Total members</h2>
                <p class="summary-value"><?= (int) $totalMemberCount ?></p>
                <p class="summary-note">All private academy member records</p>
            </article>
            <article class="card summary-card">
                <h2>Active players</h2>
                <p class="summary-value"><?= (int) $activePlayerCount ?></p>
                <p class="summary-note">Player records managed by the academy</p>
            </article>
            <article class="card summary-card">
                <h2>Pending registrations</h2>
                <p class="summary-value"><?= (int) $pendingRegistrationCount ?></p>
                <p class="summary-note">Applications waiting for staff review</p>
            </article>
            <article class="card summary-card">
                <h2>Active staff</h2>
                <p class="summary-value"><?= (int) $activeStaffCount ?></p>
                <p class="summary-note">Staff accounts currently enabled</p>
            </article>
            <article class="card summary-card">
                <h2>Published coaches</h2>
                <p class="summary-value"><?= (int) $publishedCoachCount ?></p>
                <p class="summary-note">Academy-approved public profiles</p>
            </article>
            <article class="card summary-card">
                <h2>Approved members</h2>
                <p class="summary-value"><?= (int) $approvedMemberCount ?></p>
                <p class="summary-note">Member records created from approved applications</p>
            </article>
            <article class="card summary-card">
                <h2>Active programs</h2>
                <p class="summary-value"><?= (int) $activeProgramCount ?></p>
                <p class="summary-note">Published academy programs</p>
            </article>
            <article class="card summary-card">
                <h2>Upcoming events</h2>
                <p class="summary-value"><?= (int) $upcomingEventCount ?></p>
                <p class="summary-note">Published events still to come</p>
            </article>
        </section>

        <?php if ($canViewAudit): ?>
        <section class="admin-section" aria-labelledby="activity-title">
            <h2 id="activity-title">Recent access activity</h2>
            <?php if ($recentActivity === []): ?>
                <p class="notice" role="status">No staff access activity has been recorded yet.</p>
            <?php else: ?>
                <?php
                $activityLabels = [
                    'admin.login.failed' => 'Sign-in attempt not verified',
                    'admin.login.success' => 'Staff member signed in',
                    'admin.logout' => 'Staff member signed out',
                    'setup.admin.created' => 'Initial administrator created',
                    'academy.content.updated' => 'Academy information updated',
                    'registration.status.pending' => 'Application set to pending',
                    'registration.status.under_review' => 'Application under review',
                    'registration.status.approved' => 'Application approved',
                    'registration.status.rejected' => 'Application rejected',
                    'registration.status.needs_information' => 'Application needs information',
                    'registration.status.cancelled' => 'Application cancelled',
                    'member.updated' => 'Player record updated',
                    'gallery.uploaded' => 'Gallery media uploaded',
                    'gallery.published' => 'Gallery media published',
                    'gallery.status.changed' => 'Gallery media status changed',
                    'coach.created' => 'Coach profile added',
                    'coach.updated' => 'Coach profile updated',
                    'coach.published' => 'Coach profile published',
                    'coach.status.changed' => 'Coach profile status changed',
                    'registration.submitted' => 'New player application received',
                    'program.created' => 'Program added',
                    'program.updated' => 'Program updated',
                    'program.published' => 'Program published',
                    'program.status.changed' => 'Program status changed',
                    'event.created' => 'Event added',
                    'event.updated' => 'Event updated',
                    'event.published' => 'Event published',
                    'event.status.changed' => 'Event status changed',
                    'staff.account.created' => 'Staff account created',
                    'staff.account.updated' => 'Staff account updated',
                    'staff.account.role.changed' => 'Staff role changed',
                    'staff.account.status.changed' => 'Staff account status changed',
                    'staff.account.password.reset' => 'Staff password reset',
                ];
                ?>
                <div class="table-scroll">
                    <table class="data-table">
                        <caption>Most recent staff access and setup events.</caption>
                        <thead>
                            <tr>
                                <th scope="col">Activity</th>
                                <th scope="col">Account</th>
                                <th scope="col">Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentActivity as $activity): ?>
                                <?php
                                $actorLabel = $activity['actor_name'] ?? null;
                                if (!is_string($actorLabel) || $actorLabel === '') {
                                    $actorLabel = match ($activity['action']) {
                                        'admin.login.failed' => 'Unverified sign-in',
                                        'registration.submitted' => 'Public registration',
                                        default => 'System setup',
                                    };
                                }
                                ?>
                                <tr>
                                    <td><?= Html::escape($activityLabels[$activity['action']] ?? 'Staff activity') ?></td>
                                    <td><?= Html::escape($actorLabel) ?></td>
                                    <td><?= Html::escape((string) $activity['created_at']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
        <?php endif; ?>

        <section class="admin-section" aria-labelledby="next-modules-title">
            <h2 id="next-modules-title">Academy tools</h2>
            <p class="admin-welcome">
                Update academy information, manage programs, events, and coaches, review registrations,
                maintain player records, and moderate gallery photos and videos.
            </p>
        </section>
    </main>
</body>
</html>
