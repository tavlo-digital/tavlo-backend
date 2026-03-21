<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('event_type'); // login, order_placed, order_cancelled, qr_scan, dispute_filed, etc.
            $table->string('title');
            $table->text('detail')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('device')->nullable();
            $table->timestamps();

            $table->index('customer_id');
            $table->index('event_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_activities');
    }
};
