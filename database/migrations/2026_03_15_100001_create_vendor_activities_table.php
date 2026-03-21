<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->string('event_type');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('color')->default('bg-gray-600');
            $table->string('actor')->default('System');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('vendor_id');
            $table->index('event_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_activities');
    }
};
