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
     * Build a signed public URL for a stored media value.
     *
     * The URL is signed with config('media.secret') and expires after
     * config('media.ttl') seconds. Without a valid &exp + &sig, the
     * /media/{path} endpoint returns 403.
     */
    public function url(?string $value, ?int $ttlSeconds = null): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        // Already a full URL — only rewrite legacy /storage/ paths and
        // already-built /media/... URLs (so they re-point to the proxy).
        if (preg_match('#^https?://#i', $value)) {
            $path = parse_url($value, PHP_URL_PATH);
            if (is_string($path) && str_starts_with($path, '/storage/')) {
                $relative = ltrim(substr($path, strlen('/storage/')), '/');
                return $this->signedUrl($relative, $ttlSeconds);
            }
            if (is_string($path) && str_starts_with($path, '/media/')) {
                $relative = ltrim(substr($path, strlen('/media/')), '/');
                return $this->signedUrl($relative, $ttlSeconds);
            }
            return $value;
        }

        $relative = $this->toRelativePath($value);
        if ($relative === null) {
            return null;
        }

        return $this->signedUrl($relative, $ttlSeconds);
    }

    /**
     * Verify an incoming /media/{path} request signature.
     * Returns true when the signature is valid and not expired.
     */
    public function verify(string $path, ?string $exp, ?string $sig): bool
    {
        $secret = (string) config('media.secret', '');
        if ($secret === '' || $exp === null || $sig === null) {
            return false;
        }

        if (! ctype_digit((string) $exp)) {
            return false;
        }

        $expInt = (int) $exp;
        if ($expInt < time()) {
            return false;
        }

        $expected = $this->computeSignature($path, $expInt, $secret);

        return hash_equals($expected, $sig);
    }

    private function signedUrl(string $relative, ?int $ttlSeconds): string
    {
        $relative = ltrim($relative, '/');
        $publicBase = (string) config('media.public_base_url', '');

        // When a Next.js (or other) media proxy is configured, the browser
        // talks only to that proxy. It will sign the upstream request on its
        // own, so we don't need to include exp/sig here.
        if ($publicBase !== '') {
            return $publicBase . '/media/' . $relative;
        }

        // Direct-to-Laravel fallback (local dev without a proxy): include a
        // signature so the URL still works when pasted directly.
        $secret = (string) config('media.secret', '');
        $base   = url('media/' . $relative);

        if ($secret === '') {
            return $base;
        }

        $ttl = $ttlSeconds ?? (int) config('media.ttl', 3600);
        $exp = time() + max(60, $ttl);
        $sig = $this->computeSignature($relative, $exp, $secret);

        return $base . '?exp=' . $exp . '&sig=' . $sig;
    }

    private function computeSignature(string $path, int $exp, string $secret): string
    {
        return hash_hmac('sha256', trim($path, '/') . '|' . $exp, $secret);
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
