<?php

use App\Services\MediaService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Normalize vendor_settings.logo_url and cover_photo_url so they
 * store clean relative paths (e.g. "vendors/1/logo/abc.png") instead
 * of full URLs / "/storage/..." prefixes. URLs are now built on read
 * via MediaService::url().
 */
return new class extends Migration
{
    public function up(): void
    {
        $media = app(MediaService::class);

        DB::table('vendor_settings')
            ->select('id', 'logo_url', 'cover_photo_url')
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($media) {
                foreach ($rows as $row) {
                    $update = [];

                    $logo = $media->toRelativePath($row->logo_url);
                    if ($logo !== $row->logo_url) {
                        $update['logo_url'] = $logo;
                    }

                    $cover = $media->toRelativePath($row->cover_photo_url);
                    if ($cover !== $row->cover_photo_url) {
                        $update['cover_photo_url'] = $cover;
                    }

                    if (! empty($update)) {
                        DB::table('vendor_settings')->where('id', $row->id)->update($update);
                    }
                }
            });
    }

    public function down(): void
    {
        // Non-reversible: original full URLs are not retained.
    }
};
