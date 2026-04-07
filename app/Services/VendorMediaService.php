<?php

namespace App\Services;

use App\Models\Vendor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class VendorMediaService
{
    /**
     * Store a logo file and return its public URL.
     * Max 2 MB, jpg/jpeg/png/webp validated in controller.
     */
    public function uploadLogo(Vendor $vendor, UploadedFile $file): string
    {
        $this->deleteOldFiles("vendors/{$vendor->id}/logo");

        $path = $file->store("vendors/{$vendor->id}/logo", 'public');

        return asset('storage/' . $path);
    }

    /**
     * Store a cover photo and return its public URL.
     * Max 5 MB, jpg/jpeg/png/webp validated in controller.
     */
    public function uploadCoverPhoto(Vendor $vendor, UploadedFile $file): string
    {
        $this->deleteOldFiles("vendors/{$vendor->id}/cover");

        $path = $file->store("vendors/{$vendor->id}/cover", 'public');

        return asset('storage/' . $path);
    }

    /**
     * Delete all files inside a given directory on the public disk.
     */
    public function deleteOldFiles(string $directory): void
    {
        $files = Storage::disk('public')->files($directory);
        foreach ($files as $file) {
            Storage::disk('public')->delete($file);
        }
    }
}
