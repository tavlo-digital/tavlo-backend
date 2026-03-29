<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->json('items')->nullable()->after('items_count');
            $table->boolean('payment_pending')->default(false)->after('transaction_id');
            $table->boolean('payment_received')->default(false)->after('payment_pending');
            $table->timestamp('payment_confirmed_at')->nullable()->after('payment_received');
            $table->text('payment_note')->nullable()->after('payment_confirmed_at');
            $table->timestamp('ready_at')->nullable()->after('payment_note');
            $table->timestamp('picked_up_at')->nullable()->after('ready_at');
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->text('vendor_reply')->nullable()->after('text');
            $table->timestamp('vendor_replied_at')->nullable()->after('vendor_reply');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'items',
                'payment_pending',
                'payment_received',
                'payment_confirmed_at',
                'payment_note',
                'ready_at',
                'picked_up_at',
            ]);
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->dropColumn(['vendor_reply', 'vendor_replied_at']);
        });
    }
};
