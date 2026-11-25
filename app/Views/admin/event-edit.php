<?php

declare(strict_types=1);

use Cva\Support\Html;
use Cva\Support\Url;

$value = static function (string $key) use ($event): string {
    $field = $event[$key] ?? '';
    return Html::escape(is_string($field) || is_int($field) ? (string) $field : '');
};
$localDateTimeValue = static function (string $key) use ($event): string {
    $field = $event[$key] ?? '';
    if (!is_string($field)) {
        return '';
    }
    return Html::escape(substr(str_replace(' ', 'T', $field), 0, 16));
};
$statusLabels = ['draft' => 'Draft', 'published' => 'Published', 'cancelled' => 'Cancelled', 'archived' => 'Archived'];
$registrationLabels = ['open' => 'Open', 'closed' => 'Closed', 'not_required' => 'Not required'];
$mediaBasePath = Url::path('/gallery/media');
$selectedPhotoIsPublished = false;
foreach ($photoOptions as $photoOption) {
    if ((string) ($event['photo_gallery_item_id'] ?? '') === (string) $photoOption['id']) {
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
    <title><?= $event['id'] === null ? 'Add event' : 'Edit event' ?> | Central Volleyball Academy</title>
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
        <h1><?= $event['id'] === null ? 'Add event' : 'Edit event' ?></h1>
        <p><a href="<?= Html::escape(Url::path('/admin/events')) ?>">Back to events</a></p>
        <?php if (is_array($notice)): ?><p class="notice" role="status"><?= Html::escape((string) ($notice['message'] ?? '')) ?></p><?php endif; ?>
        <?php foreach ($errors as $message): ?><p class="notice notice-error" role="alert"><?= Html::escape($message) ?></p><?php endforeach; ?>
        <form class="admin-form" method="post" action="<?= Html::escape(Url::path('/admin/events/save')) ?>">
            <input type="hidden" name="csrf_token" value="<?= Html::escape($csrfToken) ?>">
            <input type="hidden" name="event_id" value="<?= $event['id'] === null ? '' : (int) $event['id'] ?>">
            <p class="field-help">Use academy-confirmed information. Times are entered as local academy time; confirm the academy's timezone before publishing events online.</p>
            <div class="field"><label class="field-label" for="event-title">Event title</label><input id="event-title" name="title" maxlength="160" required value="<?= $value('title') ?>"></div>
            <div class="field"><label class="field-label" for="event-description">Description</label><textarea id="event-description" name="description" maxlength="4000" rows="5" required><?= $value('description') ?></textarea></div>
            <div class="form-grid">
                <div class="field"><label class="field-label" for="event-start">Start date and time</label><input id="event-start" name="starts_at" type="datetime-local" required value="<?= $localDateTimeValue('starts_at') ?>"></div>
                <div class="field"><label class="field-label" for="event-end">End date and time</label><input id="event-end" name="ends_at" type="datetime-local" required value="<?= $localDateTimeValue('ends_at') ?>"></div>
            </div>
            <div class="field"><label class="field-label" for="event-location">Location</label><input id="event-location" name="location" maxlength="255" required value="<?= $value('location') ?>"></div>
            <div class="form-grid">
                <div class="field"><label class="field-label" for="event-registration">Registration status</label><select id="event-registration" name="registration_status"><?php foreach ($registrationLabels as $status => $label): ?><option value="<?= Html::escape($status) ?>"<?= ($event['registration_status'] ?? 'not_required') === $status ? ' selected' : '' ?>><?= Html::escape($label) ?></option><?php endforeach; ?></select></div>
                <div class="field"><label class="field-label" for="event-status">Event status</label><select id="event-status" name="status"><?php foreach ($statusLabels as $status => $label): ?><option value="<?= Html::escape($status) ?>"<?= ($event['status'] ?? 'draft') === $status ? ' selected' : '' ?>><?= Html::escape($label) ?></option><?php endforeach; ?></select></div>
            </div>
            <div class="field">
                <label class="field-label" for="event-photo">Approved event image (optional)</label>
                <select id="event-photo" name="photo_gallery_item_id"><option value="">No event image</option><?php foreach ($photoOptions as $photo): ?><option value="<?= (int) $photo['id'] ?>"<?= (string) ($event['photo_gallery_item_id'] ?? '') === (string) $photo['id'] ? ' selected' : '' ?>><?= Html::escape($photo['title']) ?></option><?php endforeach; ?></select>
                <p class="field-help">Only published gallery images are available. Upload and approve an image in Gallery before selecting it here.</p>
                <?php if ($selectedPhotoIsPublished): ?><img class="coach-photo-preview" src="<?= Html::escape($mediaBasePath . '?id=' . (int) $event['photo_gallery_item_id']) ?>" alt="Current published event photograph" loading="lazy"><?php endif; ?>
            </div>
            <label class="checkbox-field"><input type="checkbox" name="academy_approval" value="1"><span>I confirm these event details and the selected image are academy-approved for public display.</span></label>
            <label class="checkbox-field"><input type="checkbox" name="cancel_confirmation" value="1"><span>If the event status above is Cancelled, I confirm that decision. A previously published cancelled event remains visible with a cancellation notice.</span></label>
            <button class="button" type="submit">Save event</button>
        </form>
    </main>
</body>
</html>
