<?php

declare(strict_types=1);

use Cva\Support\Html;
use Cva\Support\Url;

$statusLabels = ['active' => 'Active', 'inactive' => 'Inactive'];
$roleLabels = ['admin' => 'Administrator', 'staff' => 'Academy Staff'];
$csrf = Html::escape($csrfToken);

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Staff accounts | Central Volleyball Academy</title>
    <link rel="stylesheet" href="<?= Html::escape(Url::path('/assets/css/site.css')) ?>">
</head>
<body>
    <a class="skip-link" href="#main-content">Skip to content</a>
    <header class="site-header">
        <div class="container header-content">
            <a class="wordmark" href="<?= Html::escape(Url::path('/admin')) ?>">Central Volleyball Academy</a>
            <form method="post" action="<?= Html::escape(Url::path('/admin/logout')) ?>">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <button class="button button-secondary button-small" type="submit">Sign out</button>
            </form>
        </div>
    </header>
    <?php require __DIR__ . '/_navigation.php'; ?>
    <main class="container admin-content" id="main-content">
        <p class="eyebrow">Administration</p>
        <h1>Staff accounts</h1>
        <p class="admin-welcome">Create academy staff accounts, assign roles, deactivate access, and reset a password when needed. Staff accounts cannot manage users or view the audit log.</p>
        <?php if (is_array($notice)): ?>
            <p class="notice<?= ($notice['kind'] ?? '') === 'error' ? ' notice-error' : '' ?>" role="status"><?= Html::escape((string) ($notice['message'] ?? '')) ?></p>
        <?php endif; ?>
        <?php foreach ($errors as $error): ?>
            <p class="notice notice-error" role="alert"><?= Html::escape($error) ?></p>
        <?php endforeach; ?>

        <section class="card detail-card" aria-labelledby="create-staff-title">
            <h2 id="create-staff-title">Add a staff account</h2>
            <p class="field-help">Passwords must be 12 to 72 bytes. Share the initial password through a private channel. The academy staff role can use academy workflows; only administrators can manage accounts.</p>
            <form class="admin-form" method="post" action="<?= Html::escape(Url::path('/admin/users/save')) ?>">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <input type="hidden" name="user_id" value="">
                <div class="form-grid">
                    <div class="field"><label class="field-label" for="new-staff-name">Display name</label><input id="new-staff-name" name="display_name" maxlength="160" required value="<?= Html::escape((string) $createAccount['display_name']) ?>"></div>
                    <div class="field"><label class="field-label" for="new-staff-email">Email</label><input id="new-staff-email" name="email" type="email" maxlength="254" autocomplete="off" required value="<?= Html::escape((string) $createAccount['email']) ?>"></div>
                </div>
                <div class="form-grid">
                    <div class="field"><label class="field-label" for="new-staff-role">Role</label><select id="new-staff-role" name="role_slug" required><?php foreach ($roles as $role): ?><option value="<?= Html::escape((string) $role['slug']) ?>"<?= $createAccount['role_slug'] === $role['slug'] ? ' selected' : '' ?>><?= Html::escape((string) $role['display_name']) ?></option><?php endforeach; ?></select></div>
                    <div class="field"><label class="field-label" for="new-staff-status">Account status</label><select id="new-staff-status" name="status" required><?php foreach ($statusLabels as $value => $label): ?><option value="<?= Html::escape($value) ?>"<?= $createAccount['status'] === $value ? ' selected' : '' ?>><?= Html::escape($label) ?></option><?php endforeach; ?></select></div>
                </div>
                <div class="field"><label class="field-label" for="new-staff-password">Initial password</label><input id="new-staff-password" name="new_password" type="password" minlength="12" maxlength="72" autocomplete="new-password" required></div>
                <button class="button" type="submit">Create account</button>
            </form>
        </section>

        <h2>Existing accounts</h2>
        <form class="admin-filter" method="get" action="<?= Html::escape(Url::path('/admin/users')) ?>" role="search">
            <div class="field"><label class="field-label" for="staff-search">Search name or email</label><input id="staff-search" name="q" type="search" maxlength="120" value="<?= Html::escape($search) ?>"></div>
            <div class="field"><label class="field-label" for="staff-status-filter">Account status</label><select id="staff-status-filter" name="status"><?php foreach (['all' => 'All statuses', 'active' => 'Active', 'inactive' => 'Inactive'] as $value => $label): ?><option value="<?= Html::escape($value) ?>"<?= $status === $value ? ' selected' : '' ?>><?= Html::escape($label) ?></option><?php endforeach; ?></select></div>
            <div class="admin-filter-actions"><button class="button" type="submit">Apply filters</button><a class="button button-secondary" href="<?= Html::escape(Url::path('/admin/users')) ?>">Clear</a></div>
        </form>

        <p class="result-count" role="status"><?= (int) $totalRows ?> <?= $totalRows === 1 ? 'account' : 'accounts' ?> found.</p>
        <?php if ($staffAccounts === []): ?>
            <p class="notice" role="status">No staff accounts match these filters.</p>
        <?php else: ?>
            <div class="table-scroll">
                <table class="data-table">
                    <caption>Change an account's name, email, role, status, or password. Your own role and status are protected.</caption>
                    <thead><tr><th scope="col">Name</th><th scope="col">Email</th><th scope="col">Role</th><th scope="col">Status</th><th scope="col">Last sign-in</th><th scope="col">Set new password</th><th scope="col">Save</th></tr></thead>
                    <tbody>
                    <?php foreach ($staffAccounts as $staff): ?>
                        <?php $formId = 'staff-account-' . (int) $staff['id']; $isSelf = (int) $staff['id'] === $currentUserId; $roleSlug = (string) ($staff['role_slug'] ?? 'staff'); ?>
                        <tr>
                            <td>
                                <form id="<?= Html::escape($formId) ?>" method="post" action="<?= Html::escape(Url::path('/admin/users/save')) ?>">
                                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                    <input type="hidden" name="user_id" value="<?= (int) $staff['id'] ?>">
                                    <label class="visually-hidden" for="staff-name-<?= (int) $staff['id'] ?>">Display name</label>
                                    <input id="staff-name-<?= (int) $staff['id'] ?>" name="display_name" maxlength="160" required value="<?= Html::escape((string) $staff['display_name']) ?>">
                                </form>
                            </td>
                            <td><label class="visually-hidden" for="staff-email-<?= (int) $staff['id'] ?>">Email</label><input id="staff-email-<?= (int) $staff['id'] ?>" type="email" name="email" form="<?= Html::escape($formId) ?>" maxlength="254" required value="<?= Html::escape((string) $staff['email']) ?>"></td>
                            <td>
                                <?php if ($isSelf): ?>
                                    <input type="hidden" name="role_slug" form="<?= Html::escape($formId) ?>" value="<?= Html::escape($roleSlug) ?>">
                                    <?= Html::escape($roleLabels[$roleSlug] ?? 'No role') ?>
                                <?php else: ?>
                                    <label class="visually-hidden" for="staff-role-<?= (int) $staff['id'] ?>">Role</label><select id="staff-role-<?= (int) $staff['id'] ?>" name="role_slug" form="<?= Html::escape($formId) ?>"><?php foreach ($roles as $role): ?><option value="<?= Html::escape((string) $role['slug']) ?>"<?= $roleSlug === $role['slug'] ? ' selected' : '' ?>><?= Html::escape((string) $role['display_name']) ?></option><?php endforeach; ?></select>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($isSelf): ?>
                                    <input type="hidden" name="status" form="<?= Html::escape($formId) ?>" value="<?= Html::escape((string) $staff['status']) ?>">
                                    <?= Html::escape($statusLabels[$staff['status']] ?? 'Unknown') ?>
                                <?php else: ?>
                                    <label class="visually-hidden" for="staff-status-<?= (int) $staff['id'] ?>">Account status</label><select id="staff-status-<?= (int) $staff['id'] ?>" name="status" form="<?= Html::escape($formId) ?>"><?php foreach ($statusLabels as $value => $label): ?><option value="<?= Html::escape($value) ?>"<?= $staff['status'] === $value ? ' selected' : '' ?>><?= Html::escape($label) ?></option><?php endforeach; ?></select>
                                <?php endif; ?>
                            </td>
                            <td><?= Html::escape((string) ($staff['last_login_at'] ?? 'Never')) ?></td>
                            <td><label class="visually-hidden" for="staff-password-<?= (int) $staff['id'] ?>">New password, leave blank to keep current password</label><input id="staff-password-<?= (int) $staff['id'] ?>" type="password" name="new_password" form="<?= Html::escape($formId) ?>" minlength="12" maxlength="72" autocomplete="new-password" value=""></td>
                            <td><button class="button button-small" type="submit" form="<?= Html::escape($formId) ?>">Save</button></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($totalPages > 1): ?>
                <nav class="pagination" aria-label="Staff account pages">
                    <?php if ($page > 1): ?><a href="<?= Html::escape(Url::path('/admin/users')) ?>?<?= Html::escape(http_build_query(['q' => $search, 'status' => $status, 'page' => $page - 1])) ?>">Previous</a><?php endif; ?>
                    <?php foreach ($paginationPages as $pageNumber): ?>
                        <?php if ($pageNumber === $page): ?><span aria-current="page">Page <?= (int) $pageNumber ?></span><?php else: ?><a href="<?= Html::escape(Url::path('/admin/users')) ?>?<?= Html::escape(http_build_query(['q' => $search, 'status' => $status, 'page' => $pageNumber])) ?>">Page <?= (int) $pageNumber ?></a><?php endif; ?>
                    <?php endforeach; ?>
                    <?php if ($page < $totalPages): ?><a href="<?= Html::escape(Url::path('/admin/users')) ?>?<?= Html::escape(http_build_query(['q' => $search, 'status' => $status, 'page' => $page + 1])) ?>">Next</a><?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </main>
</body>
</html>
