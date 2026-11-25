<?php

declare(strict_types=1);

use Cva\Support\Html;
use Cva\Support\Url;

$statusLabels = ['draft' => 'Draft', 'published' => 'Published', 'archived' => 'Archived'];

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Coaches | Central Volleyball Academy</title>
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
        <p class="eyebrow">Academy-approved coaching staff</p>
        <h1>Coaches</h1>
        <p class="admin-welcome">Add verified biographies, roles, qualifications, and photographs. Profiles stay private drafts until a staff member confirms the information is approved for publication.</p>
        <?php if (is_array($notice)): ?><p class="notice" role="status"><?= Html::escape((string) ($notice['message'] ?? '')) ?></p><?php endif; ?>
        <p><a class="button" href="<?= Html::escape(Url::path('/admin/coaches/edit')) ?>">Add coach profile</a></p>
        <form class="admin-filter" method="get" action="<?= Html::escape(Url::path('/admin/coaches')) ?>" role="search">
            <div class="field">
                <label class="field-label" for="coach-search">Search name or role</label>
                <input id="coach-search" name="q" type="search" maxlength="120" value="<?= Html::escape($search) ?>">
            </div>
            <div class="field">
                <label class="field-label" for="coach-status">Profile status</label>
                <select id="coach-status" name="status">
                    <option value="all"<?= $status === null ? ' selected' : '' ?>>All statuses</option>
                    <?php foreach ($statusLabels as $value => $label): ?>
                        <option value="<?= Html::escape($value) ?>"<?= $status === $value ? ' selected' : '' ?>><?= Html::escape($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="admin-filter-actions">
                <button class="button" type="submit">Apply filters</button>
                <a class="button button-secondary" href="<?= Html::escape(Url::path('/admin/coaches')) ?>">Clear</a>
            </div>
        </form>
        <p class="result-count" role="status"><?= (int) $totalRows ?> <?= $totalRows === 1 ? 'profile' : 'profiles' ?> found.</p>
        <?php if ($coaches === []): ?>
            <p class="notice" role="status">No coach profiles match these filters. Add a profile when the academy has confirmed its details.</p>
        <?php else: ?>
            <div class="table-scroll">
                <table class="data-table">
                    <caption>Coach profiles and publication status.</caption>
                    <thead><tr><th scope="col">Coach</th><th scope="col">Role</th><th scope="col">Order</th><th scope="col">Status</th><th scope="col">Action</th></tr></thead>
                    <tbody>
                    <?php foreach ($coaches as $coach): ?>
                        <tr>
                            <th scope="row"><?= Html::escape($coach['first_name'] . ' ' . $coach['last_name']) ?></th>
                            <td><?= Html::escape($coach['role_title']) ?></td>
                            <td><?= (int) $coach['display_order'] ?></td>
                            <td><span class="status-label<?= $coach['status'] === 'published' ? ' status-label-success' : ' status-label-muted' ?>"><?= Html::escape($statusLabels[$coach['status']] ?? 'Unknown') ?></span></td>
                            <td><a href="<?= Html::escape(Url::path('/admin/coaches/edit')) ?>?id=<?= (int) $coach['id'] ?>">Edit profile</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($totalPages > 1): ?>
                <nav class="pagination" aria-label="Coach profile pages">
                    <?php if ($page > 1): ?><a href="<?= Html::escape(Url::path('/admin/coaches')) ?>?<?= Html::escape(http_build_query(['q' => $search, 'status' => $status ?? 'all', 'page' => $page - 1])) ?>">Previous</a><?php endif; ?>
                    <?php foreach ($paginationPages as $pageNumber): ?>
                        <?php if ($pageNumber === $page): ?><span aria-current="page">Page <?= (int) $pageNumber ?></span><?php else: ?><a href="<?= Html::escape(Url::path('/admin/coaches')) ?>?<?= Html::escape(http_build_query(['q' => $search, 'status' => $status ?? 'all', 'page' => $pageNumber])) ?>">Page <?= (int) $pageNumber ?></a><?php endif; ?>
                    <?php endforeach; ?>
                    <?php if ($page < $totalPages): ?><a href="<?= Html::escape(Url::path('/admin/coaches')) ?>?<?= Html::escape(http_build_query(['q' => $search, 'status' => $status ?? 'all', 'page' => $page + 1])) ?>">Next</a><?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </main>
</body>
</html>
