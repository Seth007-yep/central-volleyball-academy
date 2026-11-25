<?php

declare(strict_types=1);

namespace Cva\Controllers\Public;

use Cva\Repositories\GalleryRepository;
use Cva\Support\Database;

/** Stream only published, allowlisted media from private storage. */
final class GalleryMediaController
{
    private const MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'video/mp4' => 'mp4',
        'video/webm' => 'webm',
    ];

    public function show(): void
    {
        $this->stream(true);
    }

    /** Stream unpublished items only through the protected admin route. */
    public function preview(): void
    {
        $this->stream(false);
    }

    private function stream(bool $publishedOnly): void
    {
        $rawId = $_GET['id'] ?? null;
        $id = is_string($rawId) ? filter_var($rawId, FILTER_VALIDATE_INT) : false;
        if ($id === false || $id < 1) {
            http_response_code(404);
            exit;
        }

        $settings = require dirname(__DIR__, 3) . '/config/database.php';
        $repository = new GalleryRepository(Database::connect($settings));
        $item = $publishedOnly
            ? $repository->findPublished((int) $id)
            : $repository->findForAdmin((int) $id);
        if ($item === null || !isset(self::MIME_EXTENSIONS[$item['mime_type']])) {
            http_response_code(404);
            exit;
        }

        $storagePath = getenv('GALLERY_STORAGE_PATH');
        if (!is_string($storagePath) || $storagePath === '') {
            $storagePath = dirname(__DIR__, 3) . '/storage/uploads/gallery';
        }
        $storageRoot = realpath($storagePath);
        $storageKey = (string) $item['storage_key'];
        if ($storageRoot === false || !preg_match('/\A[a-f0-9]{64}\z/', $storageKey)) {
            http_response_code(404);
            exit;
        }

        $filePath = $storageRoot . DIRECTORY_SEPARATOR
            . $storageKey . '.' . self::MIME_EXTENSIONS[$item['mime_type']];
        $resolvedFile = realpath($filePath);
        if ($resolvedFile === false || !str_starts_with($resolvedFile, $storageRoot . DIRECTORY_SEPARATOR) || !is_file($resolvedFile)) {
            http_response_code(404);
            exit;
        }

        $fileSize = filesize($resolvedFile);
        if (!is_int($fileSize) || $fileSize < 1) {
            http_response_code(404);
            exit;
        }

        $start = 0;
        $end = $fileSize - 1;
        $range = $_SERVER['HTTP_RANGE'] ?? '';
        if (is_string($range) && $range !== '') {
            if (!preg_match('/\Abytes=(\d*)-(\d*)\z/', trim($range), $matches)) {
                $this->unsatisfiableRange($fileSize);
            }
            if ($matches[1] === '' && $matches[2] === '') {
                $this->unsatisfiableRange($fileSize);
            }
            if ($matches[1] === '') {
                $suffixLength = (int) $matches[2];
                if ($suffixLength < 1) {
                    $this->unsatisfiableRange($fileSize);
                }
                $start = max(0, $fileSize - $suffixLength);
            } else {
                $start = (int) $matches[1];
                if ($matches[2] !== '') {
                    $end = min($fileSize - 1, (int) $matches[2]);
                }
            }
            if ($start >= $fileSize || $end < $start) {
                $this->unsatisfiableRange($fileSize);
            }
            http_response_code(206);
            header('Content-Range: bytes ' . $start . '-' . $end . '/' . $fileSize);
        }

        header('Content-Type: ' . $item['mime_type']);
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: inline; filename="academy-media.' . self::MIME_EXTENSIONS[$item['mime_type']] . '"');
        header('Accept-Ranges: bytes');
        header('Cache-Control: public, max-age=3600');
        header('Content-Length: ' . ($end - $start + 1));

        $handle = fopen($resolvedFile, 'rb');
        if ($handle === false) {
            http_response_code(404);
            exit;
        }
        fseek($handle, $start);
        $remaining = $end - $start + 1;
        while ($remaining > 0 && !feof($handle)) {
            $chunk = fread($handle, min(8192, $remaining));
            if ($chunk === false || $chunk === '') {
                break;
            }
            echo $chunk;
            $remaining -= strlen($chunk);
        }
        fclose($handle);
        exit;
    }

    private function unsatisfiableRange(int $fileSize): void
    {
        http_response_code(416);
        header('Content-Range: bytes */' . $fileSize);
        exit;
    }
}
