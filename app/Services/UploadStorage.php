<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Upload storage wrapper.
 *
 * This service wraps Laravel's native Storage facade to maintain compatibility
 * with legacy uploads (local and S3/R2).
 */
class UploadStorage
{
    private string $disk;

    public function __construct()
    {
        $this->disk = config('filesystems.default', 'local');
    }

    /**
     * Upload a temporary file and return a public URL.
     */
    public function upload(string $tmpPath, string $filename, string $mime, string $subdir = ''): string
    {
        if (! is_file($tmpPath)) {
            throw new RuntimeException('Upload failed: temporary file is missing.', 422);
        }

        $path = ltrim($this->buildPath($subdir, $filename), '/');
        $stream = fopen($tmpPath, 'rb');

        if ($stream === false) {
            throw new RuntimeException('Upload failed: could not read temporary file.', 422);
        }

        try {
            Storage::disk($this->disk)->writeStream($path, $stream, ['mimetype' => $mime]);
        } catch (Throwable $e) {
            throw new RuntimeException('Upload failed: '.$e->getMessage(), 500);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        return Storage::disk($this->disk)->url($path);
    }

    /**
     * Delete an uploaded object by its public URL.
     * Returns true when a managed object was found and deletion was attempted.
     */
    public function deleteByUrl(?string $publicUrl): bool
    {
        $url = trim((string) $publicUrl);
        if ($url === '') {
            return false;
        }

        $path = $this->extractStoragePathFromPublicUrl($url);
        if ($path === null) {
            return false;
        }

        if (! Storage::disk($this->disk)->exists($path)) {
            return true;
        }

        Storage::disk($this->disk)->delete($path);

        return true;
    }

    private function buildPath(string $subdir, string $filename): string
    {
        $safeName = basename($filename);
        $dir = trim($subdir, "/\\ \t\n\r\0\x0B");

        return $dir !== '' ? ($dir.'/'.$safeName) : $safeName;
    }

    private function extractStoragePathFromPublicUrl(string $publicUrl): ?string
    {
        // Try to reverse the url() generation logic of Laravel's disks
        $disk = Storage::disk($this->disk);

        // For Local disk, the URL is typically /storage/{path} or APP_URL/storage/{path}
        // For S3/R2, the URL is typically the cloud URL.
        // We'll compare it against the root URL of the disk to extract the path.

        try {
            // Generate URL for a dummy file to find the base prefix
            $dummyUrl = $disk->url('DUMMY_FILE_12345.txt');
            $basePrefix = str_replace('DUMMY_FILE_12345.txt', '', $dummyUrl);

            if (str_starts_with($publicUrl, $basePrefix)) {
                return ltrim(substr($publicUrl, strlen($basePrefix)), '/');
            }

            // Fallback: If local disk and using relative paths
            if ($this->disk === 'local' || $this->disk === 'public') {
                $parsedUrl = parse_url($publicUrl, PHP_URL_PATH);
                if ($parsedUrl) {
                    $path = preg_replace('#^/storage/#', '', $parsedUrl);
                    if ($path && $path !== $parsedUrl) {
                        return ltrim($path, '/');
                    }
                }
            }
        } catch (Throwable) {
            // Silently fallback if URL parsing fails
        }

        return null;
    }
}
