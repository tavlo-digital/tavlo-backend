<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $usedSlugs = DB::table('vendors')
            ->whereNotNull('slug')
            ->where('slug', '!=', '')
            ->pluck('slug')
            ->mapWithKeys(fn (string $slug) => [strtolower($slug) => true])
            ->all();

        DB::table('vendors')
            ->where(fn ($query) => $query->whereNull('slug')->orWhereRaw("TRIM(slug) = ''"))
            ->orderBy('id')
            ->chunkById(100, function ($vendors) use (&$usedSlugs): void {
                foreach ($vendors as $vendor) {
                    $source = $vendor->restaurant_name
                        ?: $vendor->name
                        ?: $vendor->vendor_public_id
                        ?: 'vendor-'.$vendor->id;
                    $base = Str::limit(Str::slug((string) $source) ?: 'vendor-'.$vendor->id, 240, '');
                    $slug = $base;
                    $suffix = 2;

                    while (isset($usedSlugs[strtolower($slug)])) {
                        $ending = '-'.$suffix++;
                        $slug = Str::limit($base, 255 - strlen($ending), '').$ending;
                    }

                    DB::table('vendors')->where('id', $vendor->id)->update(['slug' => $slug]);
                    $usedSlugs[strtolower($slug)] = true;
                }
            });

        Schema::table('vendors', function (Blueprint $table) {
            $table->string('slug')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->string('slug')->nullable()->change();
        });
    }
};
