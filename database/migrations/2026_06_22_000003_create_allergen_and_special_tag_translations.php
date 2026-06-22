<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('allergen_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('allergen_id')->constrained()->cascadeOnDelete();
            $table->string('language', 10);
            $table->string('name');
            $table->timestamps();

            $table->unique(['allergen_id', 'language'], 'allergen_lang_unique');
        });

        Schema::create('special_tag_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('special_tag_id')->constrained()->cascadeOnDelete();
            $table->string('language', 10);
            $table->string('label');
            $table->timestamps();

            $table->unique(['special_tag_id', 'language'], 'special_tag_lang_unique');
        });

        DB::table('allergens')->orderBy('id')->each(function ($allergen) {
            DB::table('allergen_translations')->updateOrInsert(
                ['allergen_id' => $allergen->id, 'language' => 'en'],
                ['name' => $allergen->name, 'created_at' => now(), 'updated_at' => now()]
            );
        });

        DB::table('special_tags')->orderBy('id')->each(function ($tag) {
            DB::table('special_tag_translations')->updateOrInsert(
                ['special_tag_id' => $tag->id, 'language' => 'en'],
                ['label' => $tag->label, 'created_at' => now(), 'updated_at' => now()]
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('special_tag_translations');
        Schema::dropIfExists('allergen_translations');
    }
};
