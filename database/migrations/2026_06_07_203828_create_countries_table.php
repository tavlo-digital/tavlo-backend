<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->string('code', 5)->unique();
            $table->string('name');
            $table->string('flag', 10)->nullable();
            $table->string('currency', 5)->default('EUR');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();
        DB::table('countries')->insert([
            ['code' => 'AT', 'name' => 'Austria',        'flag' => "\u{1F1E6}\u{1F1F9}", 'currency' => 'EUR', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'DE', 'name' => 'Germany',        'flag' => "\u{1F1E9}\u{1F1EA}", 'currency' => 'EUR', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'GB', 'name' => 'United Kingdom', 'flag' => "\u{1F1EC}\u{1F1E7}", 'currency' => 'GBP', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
};
