<?php

declare(strict_types=1);

namespace Cva\Controllers\Admin;

use Cva\Repositories\AuditLogRepository;
use Cva\Repositories\GalleryRepository;
use Cva\Support\Csrf;
use Cva\Support\Database;
use Cva\Support\Flash;
use Cva\Support\GalleryUploadPolicy;
use Cva\Support\Session;
use Cva\Support\Url;

/** Validate, store, and moderate academy gallery photos and videos. */
final class GalleryController
{
    public function index(): void
    {
        $settings = require dirname(__DIR__, 3) . '/config/database.php';
        $items = (new GalleryRepository(Database::connect($settings)))->pageForAdmin();
        $notice = Flash::pull();
        $csrfToken = Csrf::token();
        $mediaBasePath = Url::path('/gallery/media');
        $adminPage = 'gallery';
        require dirname(__DIR__, 2) . '/Views/admin/gallery.php';
    }

    public function upload(): void
    {
        if (!Csrf::isValid($_POST['csrf_token'] ?? null)) {
            Flash::add('error', 'This form expired. Reload the page and try again.');
            header('Location: ' . Url::path('/admin/gallery'), true, 303);
            exit;
        }

        $title = $this->boundedText($_POST['title'] ?? null, 120);
        $alternativeText = $this->boundedText($_POST['alternative_text'] ?? null, 255);
        $caption = $this->boundedText($_POST['caption'] ?? null, 1000);
        $file = $_FILES['media'] ?? null;
        if (
            $title === null || $title === ''
            || $alternativeText === null || $alternativeText === ''
            || $caption === null
            || !is_array($file)
            || !isset($file['error'], $file['tmp_name'], $file['size'])
            || !is_string($file['tmp_name'])
            || !is_int($file['size'])
            || $file['error'] !== UPLOAD_ERR_OK
            || $file['size'] < 1
            || $file['size'] > GalleryUploadPolicy::MAX_FILE_BYTES
            || !is_uploaded_file($file['tmp_name'])
        ) {
            Flash::add('error', 'Add a title, accessible description, and supported photo or video within the upload size limit.');
            header('Location: ' . Url::path('/admin/gallery'), true, 303);
            exit;
        }

        $media = GalleryUploadPolicy::inspect($file['tmp_name']);
        if (!is_array($media) || $media['file_size'] !== $file['size']) {
            Flash::add('error', 'That file is unsupported, too large, or invalid. Use a JPEG, PNG, WebP, MP4, or WebM within the listed limits.');
            header('Location: ' . Url::path('/admin/gallery'), true, 303);
            exit;
        }

        $storagePath = getenv('GALLERY_STORAGE_PATH');
        if (!is_string($storagePath) || $storagePath === '') {
            $storagePath = dirname(__DIR__, 3) . '/storage/uploads/gallery';
        }
        if (!is_dir($storagePath) && !mkdir($storagePath, 0700, true) && !is_dir($storagePath)) {
            throw new \RuntimeException('Private gallery storage is unavailable.');
        }
        $storageRoot = realpath($storagePath);
        if ($storageRoot === false || !is_writable($storageRoot)) {
            throw new \RuntimeException('Private gallery storage is unavailable.');
        }
        $publicRoot = realpath(dirname(__DIR__, 3) . '/public');
        if ($publicRoot === false) {
            throw new \RuntimeException('Private gallery storage is unavailable.');
        }
        $publicPrefix = rtrim($publicRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (
            strcasecmp($storageRoot, $publicRoot) === 0
            || strncasecmp($storageRoot . DIRECTORY_SEPARATOR, $publicPrefix, strlen($publicPrefix)) === 0
        ) {
            throw new \RuntimeException('Private gallery storage is unavailable.');
        }

        $settings = require dirname(__DIR__, 3) . '/config/database.php';
        $database = Database::connect($settings);

        // Never use the browser-supplied filename; storage keys contain only random hex.
        $storageKey = bin2hex(random_bytes(32));
        $target = $storageRoot . DIRECTORY_SEPARATOR . $storageKey . '.' . $media['extension'];
        if (!move_uploaded_file($file['tmp_name'], $target)) {
            throw new \RuntimeException('The uploaded media could not be stored.');
        }

        try {
            $database->beginTransaction();
            $actor = Session::authenticatedUser();
            $itemId = (new GalleryRepository($database))->create([
                'storage_key' => $storageKey,
                'media_kind' => $media['kind'],
                'mime_type' => $media['mime_type'],
                'file_size' => $media['file_size'],
                'title' => $title,
                'alternative_text' => $alternativeText,
                'caption' => $caption === '' ? null : $caption,
                'uploaded_by_user_id' => $actor['id'] ?? null,
            ]);
            (new AuditLogRepository($database))->record(
                $actor['id'] ?? null,
                'gallery.uploaded',
                (string) $itemId,
                'gallery_item'
            );
            $database->commit();
        } catch (\Throwable $error) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            @unlink($target);
            throw $error;
        }

        Flash::add('success', 'Media uploaded as a draft. Review its description and publish it only when you have permission to share it.');
        header('Location: ' . Url::path('/admin/gallery'), true, 303);
        exit;
    }

    public function update(): void
    {
        $id = $this->requestedId($_POST['gallery_item_id'] ?? null);
        $title = $this->boundedText($_POST['title'] ?? null, 120);
        $alternativeText = $this->boundedText($_POST['alternative_text'] ?? null, 255);
        $caption = $this->boundedText($_POST['caption'] ?? null, 1000);
        $status = $_POST['status'] ?? null;
        if (
            !Csrf::isValid($_POST['csrf_token'] ?? null)
            || $id === null
            || $title === null || $title === ''
            || $alternativeText === null || $alternativeText === ''
            || $caption === null
            || !is_string($status)
            || !in_array($status, ['draft', 'published', 'archived'], true)
            || ($status === 'published' && ($_POST['publish_consent'] ?? null) !== '1')
        ) {
            Flash::add('error', 'Review the gallery fields. Publishing also requires confirmation that the academy has permission to share the media.');
            header('Location: ' . Url::path('/admin/gallery'), true, 303);
            exit;
        }

        $settings = require dirname(__DIR__, 3) . '/config/database.php';
        $database = Database::connect($settings);
        $repository = new GalleryRepository($database);
        if ($repository->findForAdmin($id) === null) {
            Flash::add('error', 'That gallery item could not be found.');
            header('Location: ' . Url::path('/admin/gallery'), true, 303);
            exit;
        }
        $actor = Session::authenticatedUser();
        $action = $status === 'published' ? 'gallery.published' : 'gallery.status.changed';
        $database->beginTransaction();
        try {
            $repository->updateDetails(
                $id,
                $title,
                $alternativeText,
                $caption === '' ? null : $caption,
                $status
            );
            (new AuditLogRepository($database))->record($actor['id'] ?? null, $action, (string) $id, 'gallery_item');
            $database->commit();
        } catch (\Throwable $error) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $error;
        }
        Flash::add('success', 'Gallery details saved.');
        header('Location: ' . Url::path('/admin/gallery'), true, 303);
        exit;
    }

    private function boundedText($value, int $maximumBytes): ?string
    {
        if (!is_string($value) || strlen($value) > $maximumBytes || preg_match('//u', $value) !== 1) {
            return null;
        }
        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $value) === 1) {
            return null;
        }

        return trim($value);
    }

    private function requestedId($value): ?int
    {
        $id = is_string($value) ? filter_var($value, FILTER_VALIDATE_INT) : false;

        return $id !== false && $id > 0 ? $id : null;
    }
}
