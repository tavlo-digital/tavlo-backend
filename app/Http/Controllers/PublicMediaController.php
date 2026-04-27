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
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}