<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('master_menu_category_translations')) {
            Schema::create('master_menu_category_translations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('master_menu_category_id')->constrained()->cascadeOnDelete();
                $table->string('language', 10);
                $table->string('name');
                $table->timestamps();

                $table->unique(
                    ['master_menu_category_id', 'language'],
                    'master_menu_category_lang_unique'
                );
            });
        }

        DB::table('master_menu_categories')->orderBy('id')->each(function ($category) {
            DB::table('master_menu_category_translations')->updateOrInsert(
                [
                    'master_menu_category_id' => $category->id,
                    'language' => 'en',
                ],
                [
                    'name' => $category->name,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_menu_category_translations');
    }
};
