<?php

declare(strict_types=1);

namespace Cva\Support;

use finfo;

/** Apply server-side MIME, size, and image-dimension rules before a media file is stored. */
final class GalleryUploadPolicy
{
    public const MAX_FILE_BYTES = 50 * 1024 * 1024;

    private const ALLOWED_MEDIA = [
        'image/jpeg' => ['kind' => 'image', 'extension' => 'jpg', 'maximum' => 8 * 1024 * 1024],
        'image/png' => ['kind' => 'image', 'extension' => 'png', 'maximum' => 8 * 1024 * 1024],
        'image/webp' => ['kind' => 'image', 'extension' => 'webp', 'maximum' => 8 * 1024 * 1024],
        'video/mp4' => ['kind' => 'video', 'extension' => 'mp4', 'maximum' => self::MAX_FILE_BYTES],
        'video/webm' => ['kind' => 'video', 'extension' => 'webm', 'maximum' => self::MAX_FILE_BYTES],
    ];

    /**
     * Return trusted storage metadata only after inspecting the bytes on disk.
     * Browser-supplied names and MIME headers are intentionally not inputs.
     */
    public static function inspect(string $temporaryPath): ?array
    {
        if (!is_file($temporaryPath) || !is_readable($temporaryPath)) {
            return null;
        }

        $actualSize = filesize($temporaryPath);
        if (!is_int($actualSize) || $actualSize < 1 || $actualSize > self::MAX_FILE_BYTES) {
            return null;
        }

        $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($temporaryPath);
        if (!is_string($mimeType) || !isset(self::ALLOWED_MEDIA[$mimeType])) {
            return null;
        }

        $media = self::ALLOWED_MEDIA[$mimeType];
        if ($actualSize > $media['maximum']) {
            return null;
        }

        if ($media['kind'] === 'image') {
            $imageInfo = getimagesize($temporaryPath);
            if (
                !is_array($imageInfo)
                || ($imageInfo['mime'] ?? '') !== $mimeType
                || (int) ($imageInfo[0] ?? 0) < 1
                || (int) ($imageInfo[1] ?? 0) < 1
                || (int) $imageInfo[0] > 10000
                || (int) $imageInfo[1] > 10000
                || (int) $imageInfo[0] * (int) $imageInfo[1] > 40000000
            ) {
                return null;
            }
        }

        return [
            'mime_type' => $mimeType,
            'kind' => $media['kind'],
            'extension' => $media['extension'],
            'file_size' => $actualSize,
        ];
    }
}
