<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plan_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_plan_id')->constrained()->cascadeOnDelete();
            $table->string('language', 10);
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['subscription_plan_id', 'language']);
        });

        Schema::create('feature_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feature_id')->constrained()->cascadeOnDelete();
            $table->string('language', 10);
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['feature_id', 'language']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_translations');
        Schema::dropIfExists('subscription_plan_translations');
    }
};
