<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stripe_webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->string('event_type');
            $table->string('stripe_payment_intent_id')->nullable();
            $table->string('stripe_event_id')->nullable();
            $table->unsignedSmallInteger('http_status');
            $table->string('outcome');
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('event_type');
            $table->index('stripe_payment_intent_id');
            $table->index('http_status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stripe_webhook_logs');
    }
};
