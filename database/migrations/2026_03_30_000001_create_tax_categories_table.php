<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_categories', function (Blueprint $table) {
            $table->id();
            $table->string('country', 5);            // ISO alpha-2: AT, DE, UK ...
            $table->string('slug')->index();          // food, beverage_non_alcoholic, beverage_alcoholic
            $table->string('name');                   // "Food", "Beverage (Non-Alcoholic)"
            $table->decimal('vat_rate', 5, 2);        // 10.00, 20.00
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['country', 'slug']);
        });

        // ---- Seed default tax categories ----
        $now = now();
        DB::table('tax_categories')->insert([
            // Austria
            ['country' => 'AT', 'slug' => 'food',                   'name' => 'Food',                        'vat_rate' => 10.00, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['country' => 'AT', 'slug' => 'beverage_non_alcoholic', 'name' => 'Beverage (Non-Alcoholic)',     'vat_rate' => 20.00, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['country' => 'AT', 'slug' => 'beverage_alcoholic',     'name' => 'Beverage (Alcoholic)',         'vat_rate' => 20.00, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            // Germany
            ['country' => 'DE', 'slug' => 'food',                   'name' => 'Food',                        'vat_rate' => 7.00,  'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['country' => 'DE', 'slug' => 'beverage_non_alcoholic', 'name' => 'Beverage (Non-Alcoholic)',     'vat_rate' => 19.00, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['country' => 'DE', 'slug' => 'beverage_alcoholic',     'name' => 'Beverage (Alcoholic)',         'vat_rate' => 19.00, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            // UK
            ['country' => 'GB', 'slug' => 'food',                   'name' => 'Food',                        'vat_rate' => 0.00,  'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['country' => 'GB', 'slug' => 'beverage_non_alcoholic', 'name' => 'Beverage (Non-Alcoholic)',     'vat_rate' => 20.00, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['country' => 'GB', 'slug' => 'beverage_alcoholic',     'name' => 'Beverage (Alcoholic)',         'vat_rate' => 20.00, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_categories');
    }
};
