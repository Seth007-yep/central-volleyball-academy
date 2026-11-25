<?php

declare(strict_types=1);

use Cva\Support\Html;
use Cva\Support\Url;

$statusLabels = ['draft' => 'Draft', 'published' => 'Published', 'cancelled' => 'Cancelled', 'archived' => 'Archived'];
$registrationLabels = ['open' => 'Open', 'closed' => 'Closed', 'not_required' => 'Not required'];

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Events | Central Volleyball Academy</title>
    <link rel="stylesheet" href="<?= Html::escape(Url::path('/assets/css/site.css')) ?>">
</head>
<body>
    <a class="skip-link" href="#main-content">Skip to content</a>
    <header class="site-header">
        <div class="container header-content">
            <a class="wordmark" href="<?= Html::escape(Url::path('/admin')) ?>">Central Volleyball Academy</a>
            <form method="post" action="<?= Html::escape(Url::path('/admin/logout')) ?>"><input type="hidden" name="csrf_token" value="<?= Html::escape(\Cva\Support\Csrf::token()) ?>"><button class="button button-secondary button-small" type="submit">Sign out</button></form>
        </div>
    </header>
    <?php require __DIR__ . '/_navigation.php'; ?>
    <main class="container admin-content" id="main-content">
        <p class="eyebrow">Academy activities</p>
        <h1>Events</h1>
        <p class="admin-welcome">Add academy-confirmed dates, times, locations, and registration details. Cancelled events that were previously published remain visible publicly with a cancellation notice.</p>
        <?php if (is_array($notice)): ?><p class="notice" role="status"><?= Html::escape((string) ($notice['message'] ?? '')) ?></p><?php endif; ?>
        <p><a class="button" href="<?= Html::escape(Url::path('/admin/events/edit')) ?>">Add event</a></p>
        <form class="admin-filter" method="get" action="<?= Html::escape(Url::path('/admin/events')) ?>" role="search">
            <div class="field"><label class="field-label" for="event-search">Search title or location</label><input id="event-search" name="q" type="search" maxlength="120" value="<?= Html::escape($search) ?>"></div>
            <div class="field"><label class="field-label" for="event-status">Event status</label><select id="event-status" name="status"><option value="all"<?= $status === null ? ' selected' : '' ?>>All statuses</option><?php foreach ($statusLabels as $value => $label): ?><option value="<?= Html::escape($value) ?>"<?= $status === $value ? ' selected' : '' ?>><?= Html::escape($label) ?></option><?php endforeach; ?></select></div>
            <div class="admin-filter-actions"><button class="button" type="submit">Apply filters</button><a class="button button-secondary" href="<?= Html::escape(Url::path('/admin/events')) ?>">Clear</a></div>
        </form>
        <p class="result-count" role="status"><?= (int) $totalRows ?> <?= $totalRows === 1 ? 'event' : 'events' ?> found.</p>
        <?php if ($events === []): ?>
            <p class="notice" role="status">No events match these filters. Add confirmed academy activities when dates and details are available.</p>
        <?php else: ?>
            <div class="table-scroll">
                <table class="data-table">
                    <caption>Academy events and registration status.</caption>
                    <thead><tr><th scope="col">Event</th><th scope="col">Starts</th><th scope="col">Location</th><th scope="col">Registration</th><th scope="col">Status</th><th scope="col">Action</th></tr></thead>
                    <tbody><?php foreach ($events as $event): ?>
                        <tr>
                            <th scope="row"><?= Html::escape($event['title']) ?></th>
                            <td><?= Html::escape((new DateTimeImmutable($event['starts_at']))->format('M j, Y g:i A')) ?></td>
                            <td><?= Html::escape($event['location']) ?></td>
                            <td><?= Html::escape($registrationLabels[$event['registration_status']] ?? 'Unknown') ?></td>
                            <td><span class="status-label<?= $event['status'] === 'published' ? ' status-label-success' : ' status-label-muted' ?>"><?= Html::escape($statusLabels[$event['status']] ?? 'Unknown') ?></span></td>
                            <td><a href="<?= Html::escape(Url::path('/admin/events/edit')) ?>?id=<?= (int) $event['id'] ?>">Edit event</a></td>
                        </tr>
                    <?php endforeach; ?></tbody>
                </table>
            </div>
            <?php if ($totalPages > 1): ?>
                <nav class="pagination" aria-label="Event pages">
                    <?php if ($page > 1): ?><a href="<?= Html::escape(Url::path('/admin/events')) ?>?<?= Html::escape(http_build_query(['q' => $search, 'status' => $status ?? 'all', 'page' => $page - 1])) ?>">Previous</a><?php endif; ?>
                    <?php foreach ($paginationPages as $pageNumber): ?><?php if ($pageNumber === $page): ?><span aria-current="page">Page <?= (int) $pageNumber ?></span><?php else: ?><a href="<?= Html::escape(Url::path('/admin/events')) ?>?<?= Html::escape(http_build_query(['q' => $search, 'status' => $status ?? 'all', 'page' => $pageNumber])) ?>">Page <?= (int) $pageNumber ?></a><?php endif; ?><?php endforeach; ?>
                    <?php if ($page < $totalPages): ?><a href="<?= Html::escape(Url::path('/admin/events')) ?>?<?= Html::escape(http_build_query(['q' => $search, 'status' => $status ?? 'all', 'page' => $page + 1])) ?>">Next</a><?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </main>
</body>
</html>
