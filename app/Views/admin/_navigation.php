<?php

declare(strict_types=1);

use Cva\Support\Html;
use Cva\Support\Session;
use Cva\Support\Url;

$adminNavigationItems = [
    'dashboard' => ['Dashboard', '/admin'],
    'academy' => ['Academy information', '/admin/academy'],
    'registrations' => ['Registrations', '/admin/registrations'],
    'players' => ['Players', '/admin/players'],
    'programs' => ['Programs', '/admin/programs'],
    'gallery' => ['Gallery', '/admin/gallery'],
    'coaches' => ['Coaches', '/admin/coaches'],
    'events' => ['Events', '/admin/events'],
];

// Only administrators receive links to the administrator-only screens.
if ((Session::authenticatedUser()['is_admin'] ?? false) === true) {
    $adminNavigationItems['users'] = ['Staff accounts', '/admin/users'];
    $adminNavigationItems['audit-logs'] = ['Audit log', '/admin/audit-logs'];
}

?>
<nav class="admin-nav" aria-label="Admin sections">
    <div class="container admin-nav-links">
        <?php foreach ($adminNavigationItems as $pageKey => [$label, $path]): ?>
            <a href="<?= Html::escape(Url::path($path)) ?>"<?= $adminPage === $pageKey ? ' aria-current="page"' : '' ?>>
                <?= Html::escape($label) ?>
            </a>
        <?php endforeach; ?>
    </div>
</nav>
