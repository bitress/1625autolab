<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * UploadStorageService.
 *
 * Replaces the original UploadStorage class.
 * Stores uploaded files to the configured disk (local by default, S3 in production).
 */
class UploadStorageService
{
    /**
     * Store an uploaded file and return its public URL.
     *
     * @param  string  $subdir  e.g. 'bookings/', 'builds/', 'vehicles/'
     */
    public function upload(UploadedFile $file, string $subdir): string
    {
        $disk = config('filesystems.default', 'public');

        $path = $file->store(
            rtrim($subdir, '/'),
            $disk
        );

        if ($path === false) {
            throw new \RuntimeException('Failed to store uploaded file.', 500);
        }

        return Storage::disk($disk)->url($path);
    }

    /**
     * Validate that an uploaded file is an accepted image type.
     * Mirrors the original inline validation in Router.php exactly.
     *
     * @param  string[]  $allowedMimes
     */
    public static function assertImageFile(UploadedFile $file, array $allowedMimes, int $maxMb): void
    {
        if (! in_array($file->getMimeType(), $allowedMimes, true)) {
            throw new \RuntimeException('Only JPEG, PNG, WebP and GIF images are accepted.', 422);
        }

        $maxBytes = $maxMb * 1024 * 1024;

        if ($file->getSize() > $maxBytes) {
            throw new \RuntimeException("Each file must be under {$maxMb} MB.", 422);
        }
    }
}
