<?php

declare(strict_types=1);

use Cva\Support\Html;
use Cva\Support\Url;

$statusLabels = ['active' => 'Active', 'inactive' => 'Inactive', 'suspended' => 'Suspended'];
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
    <title>Players | Central Volleyball Academy</title>
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
        <p class="eyebrow">Private academy records</p>
        <h1>Players</h1>
        <p class="admin-welcome">Player names and contact details are shown only to signed-in staff. Use status changes instead of deleting records.</p>
        <?php if (is_array($notice)): ?><p class="notice" role="status"><?= Html::escape((string) ($notice['message'] ?? '')) ?></p><?php endif; ?>
        <?php if (is_string($filterNotice)): ?><p class="notice notice-error" role="alert"><?= Html::escape($filterNotice) ?></p><?php endif; ?>
        <form class="admin-filter admin-filter-wide" method="get" action="<?= Html::escape(Url::path('/admin/players')) ?>" role="search">
            <div class="field">
                <label class="field-label" for="player-search">Search player name or email</label>
                <input id="player-search" name="q" type="search" maxlength="120" value="<?= Html::escape($search) ?>">
            </div>
            <div class="field">
                <label class="field-label" for="player-phone">Player or guardian phone</label>
                <input id="player-phone" name="phone" type="tel" maxlength="40" value="<?= Html::escape($phone) ?>">
            </div>
            <div class="field">
                <label class="field-label" for="player-program">Program interest contains</label>
                <input id="player-program" name="program" type="search" maxlength="120" value="<?= Html::escape($program) ?>">
            </div>
            <div class="field">
                <label class="field-label" for="player-date-from">Date of birth from</label>
                <input id="player-date-from" name="date_from" type="date" value="<?= Html::escape($dateFromInput) ?>">
            </div>
            <div class="field">
                <label class="field-label" for="player-date-to">Date of birth through</label>
                <input id="player-date-to" name="date_to" type="date" value="<?= Html::escape($dateToInput) ?>">
            </div>
            <div class="field">
                <label class="field-label" for="player-status">Player status</label>
                <select id="player-status" name="status">
                    <option value="all"<?= $status === null ? ' selected' : '' ?>>All statuses</option>
                    <?php foreach ($statusLabels as $value => $label): ?>
                        <option value="<?= Html::escape($value) ?>"<?= $status === $value ? ' selected' : '' ?>><?= Html::escape($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="admin-filter-actions">
                <button class="button" type="submit">Apply filters</button>
                <a class="button button-secondary" href="<?= Html::escape(Url::path('/admin/players')) ?>">Clear</a>
            </div>
        </form>
        <p class="result-count" role="status"><?= (int) $totalRows ?> <?= $totalRows === 1 ? 'player' : 'players' ?> found.</p>
        <?php if ($members === []): ?>
            <p class="notice" role="status">No player records match these filters. Approving a registration creates the first player record.</p>
        <?php else: ?>
            <div class="table-scroll">
                <table class="data-table">
                    <caption>Private player roster.</caption>
                    <thead><tr><th scope="col">Player</th><th scope="col">Date of birth</th><th scope="col">Program interest</th><th scope="col">Phone</th><th scope="col">Status</th><th scope="col">Action</th></tr></thead>
                    <tbody>
                    <?php foreach ($members as $member): ?>
                        <tr>
                            <th scope="row"><?= Html::escape($member['first_name'] . ' ' . $member['last_name']) ?></th>
                            <td><?= Html::escape((string) $member['date_of_birth']) ?></td>
                            <td><?= Html::escape((string) $member['program_interest']) ?></td>
                            <td><?= Html::escape((string) ($member['phone'] ?: 'Guardian contact on record')) ?></td>
                            <td><span class="status-label<?= $member['status'] === 'active' ? ' status-label-success' : ' status-label-muted' ?>"><?= Html::escape($statusLabels[$member['status']] ?? 'Unknown') ?></span></td>
                            <td><a href="<?= Html::escape(Url::path('/admin/players/edit')) ?>?id=<?= (int) $member['id'] ?>">Edit player</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($totalPages > 1): ?>
                <nav class="pagination" aria-label="Player pages">
                    <?php if ($page > 1): ?><a href="<?= Html::escape(Url::path('/admin/players')) ?>?<?= Html::escape(http_build_query(array_merge($paginationFilters, ['page' => $page - 1]))) ?>">Previous</a><?php endif; ?>
                    <?php foreach ($paginationPages as $pageNumber): ?>
                        <?php if ($pageNumber === $page): ?><span aria-current="page">Page <?= (int) $pageNumber ?></span><?php else: ?><a href="<?= Html::escape(Url::path('/admin/players')) ?>?<?= Html::escape(http_build_query(array_merge($paginationFilters, ['page' => $pageNumber]))) ?>">Page <?= (int) $pageNumber ?></a><?php endif; ?>
                    <?php endforeach; ?>
                    <?php if ($page < $totalPages): ?><a href="<?= Html::escape(Url::path('/admin/players')) ?>?<?= Html::escape(http_build_query(array_merge($paginationFilters, ['page' => $page + 1]))) ?>">Next</a><?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </main>
</body>
</html>
