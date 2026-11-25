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
    <title>Audit log | Central Volleyball Academy</title>
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
        <p class="eyebrow">Administration</p>
        <h1>Audit log</h1>
        <p class="admin-welcome">
            This history omits passwords, session identifiers, network addresses, and submitted form details.
        </p>

        <form class="admin-filter" method="get" action="<?= Html::escape(Url::path('/admin/audit-logs')) ?>">
            <div class="field">
                <label class="field-label" for="audit-action">Activity type</label>
                <select id="audit-action" name="action">
                    <option value="all"<?= $action === 'all' ? ' selected' : '' ?>>All activity</option>
                    <?php foreach (\Cva\Controllers\Admin\AuditLogController::ACTION_LABELS as $value => $label): ?>
                        <option value="<?= Html::escape($value) ?>"<?= $action === $value ? ' selected' : '' ?>>
                            <?= Html::escape($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="admin-filter-actions">
                <button class="button" type="submit">Apply filter</button>
                <a class="button button-secondary" href="<?= Html::escape(Url::path('/admin/audit-logs')) ?>">Clear</a>
            </div>
        </form>

        <p class="result-count" role="status">
            <?= (int) $totalRows ?> <?= $totalRows === 1 ? 'event' : 'events' ?> found.
        </p>
        <?php if ($auditEvents === []): ?>
            <p class="notice" role="status">No audit events match this filter.</p>
        <?php else: ?>
            <div class="table-scroll">
                <table class="data-table">
                    <caption>Recent administrative activity, newest first.</caption>
                    <thead>
                        <tr>
                            <th scope="col">Activity</th>
                            <th scope="col">Account</th>
                            <th scope="col">Related area</th>
                            <th scope="col">Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($auditEvents as $event): ?>
                            <?php
                            $actorLabel = $event['actor_name'] ?? null;
                            if (!is_string($actorLabel) || $actorLabel === '') {
                                $actorLabel = $event['action'] === 'admin.login.failed'
                                    ? 'Unverified sign-in'
                                    : 'System setup';
                            }
                            ?>
                            <tr>
                                <th scope="row">
                                    <?= Html::escape(\Cva\Controllers\Admin\AuditLogController::ACTION_LABELS[$event['action']] ?? 'Staff activity') ?>
                                </th>
                                <td><?= Html::escape($actorLabel) ?></td>
                                <td><?= Html::escape(ucfirst((string) $event['entity_type'])) ?></td>
                                <td><?= Html::escape((string) $event['created_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($totalPages > 1): ?>
                <nav class="pagination" aria-label="Audit log pages">
                    <?php if ($page > 1): ?>
                        <a href="<?= Html::escape(Url::path('/admin/audit-logs')) ?>?<?= Html::escape(http_build_query(['action' => $action, 'page' => $page - 1])) ?>">Previous</a>
                    <?php endif; ?>
                    <?php foreach ($paginationPages as $pageNumber): ?>
                        <?php if ($pageNumber === $page): ?>
                            <span aria-current="page">Page <?= (int) $pageNumber ?></span>
                        <?php else: ?>
                            <a href="<?= Html::escape(Url::path('/admin/audit-logs')) ?>?<?= Html::escape(http_build_query(['action' => $action, 'page' => $pageNumber])) ?>">
                                Page <?= (int) $pageNumber ?>
                            </a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <?php if ($page < $totalPages): ?>
                        <a href="<?= Html::escape(Url::path('/admin/audit-logs')) ?>?<?= Html::escape(http_build_query(['action' => $action, 'page' => $page + 1])) ?>">Next</a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </main>
</body>
</html>
