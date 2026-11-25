<?php

declare(strict_types=1);

use Cva\Support\Html;
use Cva\Support\Url;

$value = static function (string $key) use ($program): string {
    $field = $program[$key] ?? '';
    return Html::escape(is_string($field) || is_int($field) ? (string) $field : '');
};
$statusLabels = ['draft' => 'Draft', 'published' => 'Published', 'archived' => 'Archived'];

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $program['id'] === null ? 'Add program' : 'Edit program' ?> | Central Volleyball Academy</title>
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
        <p class="eyebrow">Academy training information</p>
        <h1><?= $program['id'] === null ? 'Add program' : 'Edit program' ?></h1>
        <p><a href="<?= Html::escape(Url::path('/admin/programs')) ?>">Back to programs</a></p>
        <?php if (is_array($notice)): ?><p class="notice" role="status"><?= Html::escape((string) ($notice['message'] ?? '')) ?></p><?php endif; ?>
        <?php foreach ($errors as $message): ?><p class="notice notice-error" role="alert"><?= Html::escape($message) ?></p><?php endforeach; ?>
        <form class="admin-form" method="post" action="<?= Html::escape(Url::path('/admin/programs/save')) ?>">
            <input type="hidden" name="csrf_token" value="<?= Html::escape($csrfToken) ?>">
            <input type="hidden" name="program_id" value="<?= $program['id'] === null ? '' : (int) $program['id'] ?>">
            <p class="field-help">Use academy-confirmed information. Categories, age groups, fees, and capacity are entered as supplied; no category list or currency is assumed.</p>
            <div class="form-grid">
                <div class="field"><label class="field-label" for="program-title">Program title</label><input id="program-title" name="title" maxlength="120" required value="<?= $value('title') ?>"></div>
                <div class="field"><label class="field-label" for="program-category">Category</label><input id="program-category" name="category" maxlength="80" required placeholder="Academy-confirmed category" value="<?= $value('category') ?>"></div>
            </div>
            <div class="form-grid">
                <div class="field"><label class="field-label" for="age-group">Age group (optional)</label><input id="age-group" name="age_group" maxlength="120" value="<?= $value('age_group') ?>"></div>
                <div class="field"><label class="field-label" for="capacity">Capacity (optional)</label><input id="capacity" name="capacity" type="number" min="1" max="100000" value="<?= $value('capacity') ?>"><p class="field-help">Leave blank if the academy does not set a limit.</p></div>
            </div>
            <div class="field"><label class="field-label" for="program-description">Description</label><textarea id="program-description" name="description" maxlength="4000" rows="6" required><?= $value('description') ?></textarea></div>
            <div class="field"><label class="field-label" for="fee-details">Fee information (optional)</label><input id="fee-details" name="fee_details" maxlength="255" value="<?= $value('fee_details') ?>"><p class="field-help">Enter only approved wording, including currency if applicable.</p></div>
            <div class="field"><label class="field-label" for="schedule-lines">Schedule (optional)</label><textarea id="schedule-lines" name="schedule_lines" maxlength="2550" rows="5"><?= $value('schedule_lines') ?></textarea><p class="field-help">Enter one academy-confirmed schedule per line, up to ten lines and 255 characters per line.</p></div>
            <div class="field"><label class="field-label" for="program-coaches">Published coaches (optional)</label><select id="program-coaches" name="coach_ids[]" multiple size="5">
                <?php foreach ($coachOptions as $coachOption): ?>
                    <option value="<?= (int) $coachOption['id'] ?>"<?= in_array((int) $coachOption['id'], $selectedCoachIds, true) ? ' selected' : '' ?>><?= Html::escape($coachOption['first_name'] . ' ' . $coachOption['last_name'] . ' — ' . $coachOption['role_title']) ?></option>
                <?php endforeach; ?>
            </select><p class="field-help">Only coach profiles already approved and published are assignable. Use Ctrl or Command to select more than one.</p></div>
            <div class="form-grid">
                <div class="field"><label class="field-label" for="display-order">Display order</label><input id="display-order" name="display_order" type="number" min="0" max="9999" value="<?= $value('display_order') ?>"></div>
                <div class="field"><label class="field-label" for="program-status">Status</label><select id="program-status" name="status"><?php foreach ($statusLabels as $status => $label): ?><option value="<?= Html::escape($status) ?>"<?= ($program['status'] ?? 'draft') === $status ? ' selected' : '' ?>><?= Html::escape($label) ?></option><?php endforeach; ?></select></div>
            </div>
            <label class="checkbox-field"><input type="checkbox" name="academy_approval" value="1"><span>I confirm the program details and schedule are academy-approved for public display.</span></label>
            <button class="button" type="submit">Save program</button>
        </form>
    </main>
</body>
</html>
