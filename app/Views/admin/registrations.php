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
$paginationFilters = [
    'q' => $search,
    'phone' => $phone,
    'program' => $program,
    'date_from' => $dateFromInput,
    'date_to' => $dateToInput,
    'status' => $status ?? 'all',
];

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Registrations | Central Volleyball Academy</title>
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
        <p class="eyebrow">Applicant review</p>
        <h1>Registrations</h1>
        <p class="admin-welcome">Applications are private. Approval creates a player record; changing status does not erase the application.</p>
        <?php if (is_array($notice)): ?>
            <p class="notice" role="status"><?= Html::escape((string) ($notice['message'] ?? '')) ?></p>
        <?php endif; ?>
        <?php if (is_string($filterNotice)): ?><p class="notice notice-error" role="alert"><?= Html::escape($filterNotice) ?></p><?php endif; ?>
        <form class="admin-filter admin-filter-wide" method="get" action="<?= Html::escape(Url::path('/admin/registrations')) ?>" role="search">
            <div class="field">
                <label class="field-label" for="registration-search">Search name or email</label>
                <input id="registration-search" type="search" name="q" maxlength="120" value="<?= Html::escape($search) ?>">
            </div>
            <div class="field">
                <label class="field-label" for="registration-phone">Applicant or guardian phone</label>
                <input id="registration-phone" type="tel" name="phone" maxlength="40" value="<?= Html::escape($phone) ?>">
            </div>
            <div class="field">
                <label class="field-label" for="registration-program">Program interest contains</label>
                <input id="registration-program" type="search" name="program" maxlength="120" value="<?= Html::escape($program) ?>">
            </div>
            <div class="field">
                <label class="field-label" for="registration-date-from">Received on or after</label>
                <input id="registration-date-from" type="date" name="date_from" value="<?= Html::escape($dateFromInput) ?>">
            </div>
            <div class="field">
                <label class="field-label" for="registration-date-to">Received on or before</label>
                <input id="registration-date-to" type="date" name="date_to" value="<?= Html::escape($dateToInput) ?>">
            </div>
            <div class="field">
                <label class="field-label" for="registration-status">Application status</label>
                <select id="registration-status" name="status">
                    <option value="all"<?= $status === null ? ' selected' : '' ?>>All statuses</option>
                    <?php foreach ($statusLabels as $value => $label): ?>
                        <option value="<?= Html::escape($value) ?>"<?= $status === $value ? ' selected' : '' ?>><?= Html::escape($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="admin-filter-actions">
                <button class="button" type="submit">Apply filters</button>
                <a class="button button-secondary" href="<?= Html::escape(Url::path('/admin/registrations')) ?>">Clear</a>
            </div>
        </form>
        <p class="result-count" role="status"><?= (int) $totalRows ?> <?= $totalRows === 1 ? 'application' : 'applications' ?> found.</p>
        <?php if ($registrations === []): ?>
            <p class="notice" role="status">No applications match these filters.</p>
        <?php else: ?>
            <div class="table-scroll">
                <table class="data-table">
                    <caption>Applications submitted to the academy.</caption>
                    <thead><tr><th scope="col">Applicant</th><th scope="col">Date of birth</th><th scope="col">Program interest</th><th scope="col">Contact</th><th scope="col">Status</th><th scope="col">Received</th></tr></thead>
                    <tbody>
                    <?php foreach ($registrations as $registration): ?>
                        <tr>
                            <th scope="row"><a href="<?= Html::escape(Url::path('/admin/registrations/view')) ?>?id=<?= (int) $registration['id'] ?>"><?= Html::escape($registration['first_name'] . ' ' . $registration['last_name']) ?></a></th>
                            <td><?= Html::escape((string) $registration['date_of_birth']) ?></td>
                            <td><?= Html::escape((string) $registration['program_interest']) ?></td>
                            <td><?= Html::escape((string) ($registration['email'] ?: $registration['phone'] ?: 'Guardian contact on record')) ?></td>
                            <td><?= Html::escape($statusLabels[$registration['status']] ?? 'Unknown') ?></td>
                            <td><?= Html::escape((string) $registration['created_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($totalPages > 1): ?>
                <nav class="pagination" aria-label="Registration pages">
                    <?php if ($page > 1): ?><a href="<?= Html::escape(Url::path('/admin/registrations')) ?>?<?= Html::escape(http_build_query(array_merge($paginationFilters, ['page' => $page - 1]))) ?>">Previous</a><?php endif; ?>
                    <?php foreach ($paginationPages as $pageNumber): ?>
                        <?php if ($pageNumber === $page): ?><span aria-current="page">Page <?= (int) $pageNumber ?></span><?php else: ?><a href="<?= Html::escape(Url::path('/admin/registrations')) ?>?<?= Html::escape(http_build_query(array_merge($paginationFilters, ['page' => $pageNumber]))) ?>">Page <?= (int) $pageNumber ?></a><?php endif; ?>
                    <?php endforeach; ?>
                    <?php if ($page < $totalPages): ?><a href="<?= Html::escape(Url::path('/admin/registrations')) ?>?<?= Html::escape(http_build_query(array_merge($paginationFilters, ['page' => $page + 1]))) ?>">Next</a><?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </main>
</body>
</html>
