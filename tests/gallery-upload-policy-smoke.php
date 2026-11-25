<?php

declare(strict_types=1);

// Creates disposable image/text files in the system temp directory and removes them.
// Run with: C:\xampp\php\php.exe tests\gallery-upload-policy-smoke.php
$projectRoot = dirname(__DIR__);
require $projectRoot . '/bootstrap/autoload.php';

use Cva\Support\GalleryUploadPolicy;

function expectUploadPolicyCheck(bool $condition, string $checkName): void
{
    if (!$condition) {
        throw new RuntimeException('Failed check: ' . $checkName);
    }
}

$temporaryFiles = [];
$makeTemporaryFile = static function (string $suffix = '') use (&$temporaryFiles): string {
    $base = tempnam(sys_get_temp_dir(), 'cva-upload-smoke-');
    if (!is_string($base)) {
        throw new RuntimeException('Could not create a temporary file.');
    }
    $path = $base . $suffix;
    if ($suffix !== '' && !rename($base, $path)) {
        @unlink($base);
        throw new RuntimeException('Could not name a temporary test file.');
    }
    $temporaryFiles[] = $path;

    return $path;
};

try {
    // This 1x1 PNG fixture is synthetic and is never uploaded to the application.
    $pngBytes = base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/jOQAAAAASUVORK5CYII=',
        true
    );
    if (!is_string($pngBytes)) {
        throw new RuntimeException('The local image fixture could not be decoded.');
    }

    $validImage = $makeTemporaryFile('.jpg');
    file_put_contents($validImage, $pngBytes);
    $valid = GalleryUploadPolicy::inspect($validImage);
    expectUploadPolicyCheck(
        is_array($valid)
            && $valid['mime_type'] === 'image/png'
            && $valid['extension'] === 'png'
            && $valid['file_size'] === strlen($pngBytes),
        'image type and storage extension come from file bytes, not its .jpg name'
    );

    $fakeImage = $makeTemporaryFile('.png');
    file_put_contents($fakeImage, "MZ\x00synthetic executable-like content");
    expectUploadPolicyCheck(GalleryUploadPolicy::inspect($fakeImage) === null, 'non-image content is rejected despite a .png name');

    $activeSvg = $makeTemporaryFile('.jpg');
    file_put_contents($activeSvg, '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
    expectUploadPolicyCheck(GalleryUploadPolicy::inspect($activeSvg) === null, 'SVG or active markup is not an allowed upload type');

    $oversizedImage = $makeTemporaryFile('.png');
    file_put_contents($oversizedImage, $pngBytes);
    $handle = fopen($oversizedImage, 'ab');
    if (!is_resource($handle) || !ftruncate($handle, 8 * 1024 * 1024 + 1)) {
        throw new RuntimeException('Could not construct the synthetic size-limit fixture.');
    }
    fclose($handle);
    clearstatcache(true, $oversizedImage);
    expectUploadPolicyCheck(GalleryUploadPolicy::inspect($oversizedImage) === null, 'image size above eight MiB is rejected');

    expectUploadPolicyCheck(
        GalleryUploadPolicy::inspect($projectRoot . '/missing-smoke-file') === null,
        'missing temporary paths are rejected'
    );

    fwrite(STDOUT, "Gallery upload policy checks passed (5 checks); temporary files removed.\n");
} catch (Throwable $error) {
    fwrite(STDERR, 'Gallery upload policy check failed (' . get_class($error) . ').' . "\n");
    exit(1);
} finally {
    foreach ($temporaryFiles as $temporaryFile) {
        if (is_file($temporaryFile)) {
            @unlink($temporaryFile);
        }
    }
}
