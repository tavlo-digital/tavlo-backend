<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('languages', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name', 100);
            $table->string('native_name', 100)->nullable();
            $table->string('flag', 20)->nullable();
            $table->string('direction', 3)->default('ltr');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $languages = [
            ['code' => 'en', 'name' => 'English', 'native_name' => 'English', 'flag' => '🇬🇧', 'direction' => 'ltr'],
            ['code' => 'de', 'name' => 'German', 'native_name' => 'Deutsch', 'flag' => '🇩🇪', 'direction' => 'ltr'],
            ['code' => 'it', 'name' => 'Italian', 'native_name' => 'Italiano', 'flag' => '🇮🇹', 'direction' => 'ltr'],
            ['code' => 'fr', 'name' => 'French', 'native_name' => 'Français', 'flag' => '🇫🇷', 'direction' => 'ltr'],
            ['code' => 'ar', 'name' => 'Arabic', 'native_name' => 'العربية', 'flag' => '🇸🇦', 'direction' => 'rtl'],
            ['code' => 'tr', 'name' => 'Turkish', 'native_name' => 'Türkçe', 'flag' => '🇹🇷', 'direction' => 'ltr'],
            ['code' => 'zh', 'name' => 'Chinese', 'native_name' => '中文', 'flag' => '🇨🇳', 'direction' => 'ltr'],
            ['code' => 'ja', 'name' => 'Japanese', 'native_name' => '日本語', 'flag' => '🇯🇵', 'direction' => 'ltr'],
            ['code' => 'sr', 'name' => 'Serbian', 'native_name' => 'Српски', 'flag' => '🇷🇸', 'direction' => 'ltr'],
            ['code' => 'cs', 'name' => 'Czech', 'native_name' => 'Čeština', 'flag' => '🇨🇿', 'direction' => 'ltr'],
            ['code' => 'es', 'name' => 'Spanish', 'native_name' => 'Español', 'flag' => '🇪🇸', 'direction' => 'ltr'],
            ['code' => 'nl', 'name' => 'Dutch', 'native_name' => 'Nederlands', 'flag' => '🇳🇱', 'direction' => 'ltr'],
        ];

        foreach ($languages as $sortOrder => $language) {
            DB::table('languages')->insert([
                ...$language,
                'sort_order' => $sortOrder,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('languages');
    }
};
