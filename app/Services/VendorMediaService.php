<?php

namespace App\Services;

use App\Models\Vendor;
use Illuminate\Http\UploadedFile;

/**
 * Thin vendor-scoped wrapper around the generic MediaService.
 *
 * Returns RELATIVE paths (e.g. "vendors/1/logo/abc.png") so they can be
 * persisted directly in `vendor_settings.logo_url` / `cover_photo_url`.
 * Use MediaService::url() (or the model accessor) to render the full URL.
 */
class VendorMediaService
{
    public function __construct(private readonly MediaService $media)
    {
    }

    public function uploadLogo(Vendor $vendor, UploadedFile $file): string
    {
        return $this->media->replaceInDirectory($file, "vendors/{$vendor->id}/logo");
    }

    public function uploadCoverPhoto(Vendor $vendor, UploadedFile $file): string
    {
        return $this->media->replaceInDirectory($file, "vendors/{$vendor->id}/cover");
    }

    public function uploadBackgroundImage(Vendor $vendor, UploadedFile $file): string
    {
        return $this->media->replaceInDirectory($file, "vendors/{$vendor->id}/background");
    }

    /**
     * @deprecated Use MediaService::url() directly. Kept for back-compat.
     */
    public function normalizePublicMediaUrl(?string $value): ?string
    {
        return $this->media->url($value);
    }
}
