<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->uuid('client_item_id')->nullable()->unique()->after('id');
        });

        Schema::create('customer_commands', function (Blueprint $table) {
            $table->id();
            $table->uuid('command_id')->unique();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('table_scan_session_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('sequence');
            $table->string('operation', 64);
            $table->string('status', 24)->default('processing');
            $table->json('payload');
            $table->json('response')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['table_scan_session_id', 'sequence']);
            $table->index(['table_scan_session_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_commands');

        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropUnique(['client_item_id']);
            $table->dropColumn('client_item_id');
        });
    }
};
