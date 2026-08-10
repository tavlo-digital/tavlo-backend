<?php

namespace Tests\Feature\Database;

use App\Models\Vendor;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class VendorSlugMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_backfills_unique_slugs_and_then_rejects_null_slugs(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->string('slug')->nullable()->change();
        });

        Vendor::factory()->create([
            'name' => 'Existing Restaurant',
            'restaurant_name' => 'XO Grill',
            'slug' => 'xo-grill',
        ]);
        $firstLegacyVendor = Vendor::factory()->create([
            'name' => 'First Owner',
            'restaurant_name' => 'XO Grill',
        ]);
        $secondLegacyVendor = Vendor::factory()->create([
            'name' => 'Second Owner',
            'restaurant_name' => 'XO Grill',
        ]);

        DB::table('vendors')->where('id', $firstLegacyVendor->id)->update(['slug' => null]);
        DB::table('vendors')->where('id', $secondLegacyVendor->id)->update(['slug' => '   ']);

        $migration = require database_path(
            'migrations/2026_08_10_000001_backfill_and_require_vendor_slugs.php'
        );
        $migration->up();

        $this->assertDatabaseHas('vendors', [
            'id' => $firstLegacyVendor->id,
            'slug' => 'xo-grill-2',
        ]);
        $this->assertDatabaseHas('vendors', [
            'id' => $secondLegacyVendor->id,
            'slug' => 'xo-grill-3',
        ]);

        $this->expectException(QueryException::class);
        DB::table('vendors')->where('id', $firstLegacyVendor->id)->update(['slug' => null]);
    }
}
