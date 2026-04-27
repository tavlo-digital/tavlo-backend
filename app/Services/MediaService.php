<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Generic, app-wide media handler.
 *
 * Convention:
 *  - Files live on the `public` disk under entity-scoped directories
 *    (e.g. "vendors/{id}/logo", "menu-items/{id}/photos", "customers/{id}/avatar").
 *  - Database columns store the RELATIVE path returned by store()
 *    (e.g. "vendors/1/logo/abc.png").
 *  - Public URLs are produced by url(), which routes through the
 *    `/media/{path}` endpoint served by PublicMediaController.
 *
 * Usage:
 *   $path = $media->store($request->file('logo'), "vendors/{$vendor->id}/logo");
 *   $vendor->logo_path = $path;          // store relative path
 *   $url = $media->url($vendor->logo_path); // build full URL on read
 */
class MediaService
{
    /**
     * Store a file in the given directory on the public disk
     * and return the relative path (no host, no /storage, no /media prefix).
     */
    public function store(UploadedFile $file, string $directory): string
    {
        $directory = trim($directory, '/');

        return $file->store($directory, 'public');
    }

    /**
     * Replace the contents of a "single-file slot" directory:
     * deletes everything currently in $directory, then stores the new file.
     * Useful for things like vendor logo / cover photo / user avatar
     * where only one file should ever live in the folder.
     */
    public function replaceInDirectory(UploadedFile $file, string $directory): string
    {
        $this->clearDirectory($directory);

        return $this->store($file, $directory);
    }

    /**
     * Delete a single stored file by its relative path.
     */
    public function delete(?string $path): void
    {
        $relative = $this->toRelativePath($path);
        if ($relative === null) {
            return;
        }

        $disk = Storage::disk('public');
        if ($disk->exists($relative)) {
            $disk->delete($relative);
        }
    }

    /**
     * Delete every file inside a directory (non-recursive).
     */
    public function clearDirectory(string $directory): void
    {
        $directory = trim($directory, '/');
        $disk = Storage::disk('public');

        foreach ($disk->files($directory) as $file) {
            $disk->delete($file);
        }
    }

    /**
     * Build a public URL for a stored media value.
     *
     * Returns an absolute URL pointing at the application's /media/{path}
     * endpoint. Media is served publicly — no signing or expiry is applied.
     */
    public function url(?string $value, ?int $ttlSeconds = null): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        // Already a full URL — only rewrite legacy /storage/ and /media/ paths
        // so they point at the current app host.
        if (preg_match('#^https?://#i', $value)) {
            $path = parse_url($value, PHP_URL_PATH);
            if (is_string($path) && str_starts_with($path, '/storage/')) {
                return $this->publicUrl(ltrim(substr($path, strlen('/storage/')), '/'));
            }
            if (is_string($path) && str_starts_with($path, '/media/')) {
                return $this->publicUrl(ltrim(substr($path, strlen('/media/')), '/'));
            }
            return $value;
        }

        $relative = $this->toRelativePath($value);
        if ($relative === null) {
            return null;
        }

        return $this->publicUrl($relative);
    }

    private function publicUrl(string $relative): string
    {
        return url('media/' . ltrim($relative, '/'));
    }

    /**
     * Reduce any stored value (full URL, /storage path, /media path,
     * or already-relative path) to a clean relative storage path.
     * Returns null if nothing usable can be extracted.
     */
    public function toRelativePath(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $candidate = $value;

        if (preg_match('#^https?://#i', $candidate)) {
            $path = parse_url($candidate, PHP_URL_PATH);
            if (! is_string($path)) {
                return null;
            }
            $candidate = $path;
        }

        $candidate = ltrim($candidate, '/');

        foreach (['storage/', 'media/'] as $prefix) {
            if (str_starts_with($candidate, $prefix)) {
                $candidate = substr($candidate, strlen($prefix));
                break;
            }
        }

        $candidate = trim($candidate, '/');

        return $candidate === '' ? null : $candidate;
    }
}
