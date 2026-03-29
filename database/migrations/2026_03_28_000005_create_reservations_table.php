<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->string('reservation_public_id')->unique();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('guest_name')->nullable();
            $table->string('guest_email')->nullable();
            $table->string('guest_phone')->nullable();
            $table->date('date');
            $table->time('time');
            $table->unsignedInteger('party_size')->default(2);
            $table->string('status')->default('pending'); // pending, confirmed, cancelled, completed, no_show
            $table->text('customer_note')->nullable();
            $table->text('vendor_note')->nullable();
            $table->string('table_number')->nullable();
            $table->timestamps();

            $table->index(['vendor_id', 'date']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
