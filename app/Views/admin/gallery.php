<?php

declare(strict_types=1);

use Cva\Support\Html;
use Cva\Support\Url;

$previewBasePath = Url::path('/admin/gallery/media');
$statusLabels = ['draft' => 'Draft', 'published' => 'Published', 'archived' => 'Archived'];

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Gallery | Central Volleyball Academy</title>
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
        <p class="eyebrow">Academy photos and videos</p>
        <h1>Gallery</h1>
        <p class="admin-welcome">Uploads are stored outside the public web folder. New items are drafts. Publish only media that the academy has permission to share.</p>
        <?php if (is_array($notice)): ?><p class="notice" role="status"><?= Html::escape((string) ($notice['message'] ?? '')) ?></p><?php endif; ?>

        <form class="admin-form upload-form" method="post" enctype="multipart/form-data" action="<?= Html::escape(Url::path('/admin/gallery/upload')) ?>">
            <h2>Upload photo or video</h2>
            <input type="hidden" name="csrf_token" value="<?= Html::escape($csrfToken) ?>">
            <div class="field"><label class="field-label" for="media-title">Title</label><input id="media-title" name="title" maxlength="120" required></div>
            <div class="field"><label class="field-label" for="media-alt">Accessible description</label><input id="media-alt" name="alternative_text" maxlength="255" required><p class="field-help">Describe important visual information in a photo. For a video, describe its subject.</p></div>
            <div class="field"><label class="field-label" for="media-caption">Caption (optional)</label><textarea id="media-caption" name="caption" maxlength="1000" rows="3"></textarea></div>
            <div class="field"><label class="field-label" for="media-file">Photo or video file</label><input id="media-file" name="media" type="file" accept="image/jpeg,image/png,image/webp,video/mp4,video/webm" required><p class="field-help">JPEG, PNG, or WebP photos up to 8 MB; MP4 or WebM videos up to 50 MB.</p></div>
            <button class="button" type="submit">Upload as draft</button>
        </form>

        <section class="admin-section" aria-labelledby="gallery-items-title">
            <h2 id="gallery-items-title">Uploaded media</h2>
            <?php if ($items === []): ?>
                <p class="notice" role="status">No media has been uploaded yet.</p>
            <?php else: ?>
                <div class="admin-gallery-list">
                    <?php foreach ($items as $item): ?>
                        <?php $previewUrl = $previewBasePath . '?id=' . (int) $item['id']; ?>
                        <article class="card admin-gallery-item">
                            <div class="gallery-preview">
                                <?php if ($item['media_kind'] === 'image'): ?>
                                    <img src="<?= Html::escape($previewUrl) ?>" alt="<?= Html::escape($item['alternative_text']) ?>" loading="lazy">
                                <?php else: ?>
                                    <video controls preload="metadata" aria-label="<?= Html::escape($item['alternative_text']) ?>"><source src="<?= Html::escape($previewUrl) ?>" type="<?= Html::escape($item['mime_type']) ?>">Your browser cannot play this video.</video>
                                <?php endif; ?>
                            </div>
                            <form class="admin-form gallery-edit-form" method="post" action="<?= Html::escape(Url::path('/admin/gallery/update')) ?>">
                                <input type="hidden" name="csrf_token" value="<?= Html::escape($csrfToken) ?>">
                                <input type="hidden" name="gallery_item_id" value="<?= (int) $item['id'] ?>">
                                <div class="field"><label class="field-label" for="title-<?= (int) $item['id'] ?>">Title</label><input id="title-<?= (int) $item['id'] ?>" name="title" maxlength="120" required value="<?= Html::escape($item['title']) ?>"></div>
                                <div class="field"><label class="field-label" for="alt-<?= (int) $item['id'] ?>">Accessible description</label><input id="alt-<?= (int) $item['id'] ?>" name="alternative_text" maxlength="255" required value="<?= Html::escape($item['alternative_text']) ?>"></div>
                                <div class="field"><label class="field-label" for="caption-<?= (int) $item['id'] ?>">Caption</label><textarea id="caption-<?= (int) $item['id'] ?>" name="caption" maxlength="1000" rows="2"><?= Html::escape((string) ($item['caption'] ?? '')) ?></textarea></div>
                                <div class="field"><label class="field-label" for="status-<?= (int) $item['id'] ?>">Visibility</label><select id="status-<?= (int) $item['id'] ?>" name="status"><?php foreach ($statusLabels as $value => $label): ?><option value="<?= Html::escape($value) ?>"<?= $item['status'] === $value ? ' selected' : '' ?>><?= Html::escape($label) ?></option><?php endforeach; ?></select></div>
                                <label class="checkbox-field"><input type="checkbox" name="publish_consent" value="1"> I confirm the academy has permission to publish this media.</label>
                                <button class="button button-secondary" type="submit">Save gallery item</button>
                            </form>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
