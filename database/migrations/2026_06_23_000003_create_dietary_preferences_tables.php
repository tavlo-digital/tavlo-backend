<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dietary_preferences', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->string('name');
            $table->string('icon', 50)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('dietary_preference_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dietary_preference_id')->constrained()->cascadeOnDelete();
            $table->string('language', 10);
            $table->string('name');
            $table->timestamps();

            $table->unique(['dietary_preference_id', 'language'], 'dietary_preference_lang_unique');
        });

        $preferences = [
            ['slug' => 'vegetarian', 'name' => 'Vegetarian', 'icon' => '🥬'],
            ['slug' => 'vegan', 'name' => 'Vegan', 'icon' => '🌱'],
            ['slug' => 'pescetarian', 'name' => 'Pescetarian', 'icon' => '🐟'],
        ];

        foreach ($preferences as $sortOrder => $preference) {
            $id = DB::table('dietary_preferences')->insertGetId([
                ...$preference,
                'sort_order' => $sortOrder,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('dietary_preference_translations')->insert([
                'dietary_preference_id' => $id,
                'language' => 'en',
                'name' => $preference['name'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dietary_preference_translations');
        Schema::dropIfExists('dietary_preferences');
    }
};
