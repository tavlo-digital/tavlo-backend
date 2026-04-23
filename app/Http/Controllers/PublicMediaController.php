<?php

namespace App\Http\Controllers;

use App\Services\MediaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PublicMediaController extends Controller
{
    public function __construct(private readonly MediaService $media)
    {
    }

    public function show(Request $request, string $path): BinaryFileResponse
    {
        // Prevent path traversal attempts.
        if (str_contains($path, '..')) {
            abort(404);
        }

        $secret = (string) config('media.secret', '');

        // In any non-local environment a secret MUST be configured; otherwise
        // we'd silently serve everything publicly.
        if ($secret === '') {
            if (app()->environment('production')) {
                abort(500, 'Media secret is not configured.');
            }
        } else {
            // 1. The request must come from a trusted client that holds the
            //    shared secret (i.e. the Next.js proxy). Browsers hitting
            //    this URL directly will not send this header.
            $clientHeader = (string) $request->header('X-Media-Client', '');
            if (! hash_equals($secret, $clientHeader)) {
                abort(403, 'Media access is restricted to trusted clients.');
            }

            // 2. The URL itself must also carry a valid signature.
            if (! $this->media->verify($path, $request->query('exp'), $request->query('sig'))) {
                abort(403, 'Invalid or expired media signature.');
            }
        }

        $disk = Storage::disk('public');
        $resolvedPath = $path;

        if (! $disk->exists($resolvedPath)) {
            // Fallback for stale URLs: if the requested file was replaced,
            // serve the current file from the same directory.
            $directory = trim(dirname($path), '/');
            if ($directory !== '' && $directory !== '.') {
                $candidates = array_values(array_filter($disk->files($directory), fn ($file) => is_string($file) && $file !== ''));
                if (count($candidates) === 1) {
                    $resolvedPath = $candidates[0];
                } else {
                    abort(404);
                }
            } else {
                abort(404);
            }
        }

        $absolutePath = $disk->path($resolvedPath);

        return response()->file($absolutePath, [
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}