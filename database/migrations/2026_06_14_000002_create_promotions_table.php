<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('vendor_id');
            $table->foreign('vendor_id')->references('id')->on('vendors')->cascadeOnDelete();
            $table->string('name', 255);
            $table->string('type'); // 'happy-hour', 'weekend-special', 'item-discount'
            $table->text('description');
            $table->string('discount_type'); // 'percentage', 'fixed'
            $table->decimal('discount_value', 8, 2);
            $table->date('start_date');
            $table->date('end_date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->json('active_days')->nullable(); // ['Monday', 'Tuesday', ...]
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};
