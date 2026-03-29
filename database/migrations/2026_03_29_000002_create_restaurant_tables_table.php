<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurant_tables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->string('name');             // e.g. "Table 1"
            $table->string('qr_token')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamp('qr_created_at')->useCurrent();
            $table->timestamp('last_scanned_at')->nullable();
            $table->timestamps();

            $table->unique(['vendor_id', 'number']);
            $table->index('vendor_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_tables');
    }
};
