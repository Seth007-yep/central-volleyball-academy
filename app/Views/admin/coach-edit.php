<?php

declare(strict_types=1);

use Cva\Support\Html;
use Cva\Support\Url;

$value = static function (string $key) use ($coach): string {
    $field = $coach[$key] ?? '';
    return Html::escape(is_string($field) || is_int($field) ? (string) $field : '');
};
$statusLabels = ['draft' => 'Draft', 'published' => 'Published', 'archived' => 'Archived'];
$mediaBasePath = Url::path('/gallery/media');
$selectedPhotoIsPublished = false;
foreach ($photoOptions as $photoOption) {
    if ((string) ($coach['photo_gallery_item_id'] ?? '') === (string) $photoOption['id']) {
        $selectedPhotoIsPublished = true;
        break;
    }
}

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $coach['id'] === null ? 'Add coach' : 'Edit coach' ?> | Central Volleyball Academy</title>
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
        <h1><?= $coach['id'] === null ? 'Add coach profile' : 'Edit coach profile' ?></h1>
        <p><a href="<?= Html::escape(Url::path('/admin/coaches')) ?>">Back to coaches</a></p>
        <?php if (is_array($notice)): ?><p class="notice" role="status"><?= Html::escape((string) ($notice['message'] ?? '')) ?></p><?php endif; ?>
        <?php foreach ($errors as $message): ?><p class="notice notice-error" role="alert"><?= Html::escape($message) ?></p><?php endforeach; ?>

        <form class="admin-form" method="post" action="<?= Html::escape(Url::path('/admin/coaches/save')) ?>">
            <input type="hidden" name="csrf_token" value="<?= Html::escape($csrfToken) ?>">
            <input type="hidden" name="coach_id" value="<?= $coach['id'] === null ? '' : (int) $coach['id'] ?>">
            <p class="field-help">Use information the academy has confirmed. Do not guess or invent a coach's history, credentials, or qualifications.</p>
            <div class="form-grid">
                <div class="field"><label class="field-label" for="first-name">First name</label><input id="first-name" name="first_name" maxlength="80" required value="<?= $value('first_name') ?>"></div>
                <div class="field"><label class="field-label" for="last-name">Last name</label><input id="last-name" name="last_name" maxlength="80" required value="<?= $value('last_name') ?>"></div>
            </div>
            <div class="field"><label class="field-label" for="role-title">Role</label><input id="role-title" name="role_title" maxlength="120" required placeholder="For example: Head Coach" value="<?= $value('role_title') ?>"></div>
            <div class="field"><label class="field-label" for="biography">Biography</label><textarea id="biography" name="biography" maxlength="4000" rows="6" required><?= $value('biography') ?></textarea><p class="field-help">Plain text, up to 4,000 characters.</p></div>
            <div class="field"><label class="field-label" for="experience">Coaching experience (optional)</label><textarea id="experience" name="experience" maxlength="2000" rows="4"><?= $value('experience') ?></textarea><p class="field-help">Add only academy-approved experience, such as confirmed coaching history. Plain text, up to 2,000 characters.</p></div>
            <div class="field"><label class="field-label" for="qualifications">Qualifications (optional)</label><textarea id="qualifications" name="qualifications" maxlength="2000" rows="4"><?= $value('qualifications') ?></textarea><p class="field-help">List only qualifications verified by the academy.</p></div>
            <div class="field">
                <label class="field-label" for="coach-photo">Profile photograph (optional)</label>
                <select id="coach-photo" name="photo_gallery_item_id">
                    <option value="">No profile photograph</option>
                    <?php foreach ($photoOptions as $photo): ?>
                        <option value="<?= (int) $photo['id'] ?>"<?= (string) ($coach['photo_gallery_item_id'] ?? '') === (string) $photo['id'] ? ' selected' : '' ?>><?= Html::escape($photo['title']) ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="field-help">Only published photos in the gallery are offered here. To add a photo, first upload it in Gallery, then review and publish it after the academy confirms permission to share it.</p>
                <?php if ($selectedPhotoIsPublished): ?>
                    <img class="coach-photo-preview" src="<?= Html::escape($mediaBasePath . '?id=' . (int) $coach['photo_gallery_item_id']) ?>" alt="Current published profile photograph" loading="lazy">
                <?php endif; ?>
            </div>
            <div class="form-grid">
                <div class="field"><label class="field-label" for="display-order">Display order</label><input id="display-order" name="display_order" type="number" min="0" max="9999" value="<?= $value('display_order') ?>"><p class="field-help">Lower numbers appear first. Ties are sorted by last name.</p></div>
                <div class="field"><label class="field-label" for="coach-status">Profile status</label><select id="coach-status" name="status"><?php foreach ($statusLabels as $status => $label): ?><option value="<?= Html::escape($status) ?>"<?= ($coach['status'] ?? 'draft') === $status ? ' selected' : '' ?>><?= Html::escape($label) ?></option><?php endforeach; ?></select></div>
            </div>
            <label class="checkbox-field"><input type="checkbox" name="academy_approval" value="1"><span>I confirm the biography, experience, role, qualifications, and selected photo are academy-approved for public display.</span></label>
            <button class="button" type="submit">Save coach profile</button>
        </form>
    </main>
</body>
</html>
