<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_category_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tax_category_id')->constrained()->cascadeOnDelete();
            $table->string('language', 10);
            $table->string('name');
            $table->timestamps();

            $table->unique(['tax_category_id', 'language']);
        });

        DB::table('tax_categories')->orderBy('id')->each(function ($category) {
            DB::table('tax_category_translations')->updateOrInsert(
                [
                    'tax_category_id' => $category->id,
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
        Schema::dropIfExists('tax_category_translations');
    }
};
