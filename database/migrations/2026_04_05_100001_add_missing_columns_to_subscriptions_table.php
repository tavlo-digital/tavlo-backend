<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('subscriptions', 'stripe_customer_id')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->string('stripe_customer_id')->nullable()->after('stripe_subscription_id');
            });
        }

        if (! Schema::hasColumn('subscriptions', 'cancelled_at')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->timestamp('cancelled_at')->nullable()->after('stripe_customer_id');
            });
        }

        if (! Schema::hasColumn('subscriptions', 'paused_at')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->timestamp('paused_at')->nullable()->after('cancelled_at');
            });
        }
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $cols = [];
            if (Schema::hasColumn('subscriptions', 'stripe_customer_id')) {
                $cols[] = 'stripe_customer_id';
            }
            if (Schema::hasColumn('subscriptions', 'cancelled_at')) {
                $cols[] = 'cancelled_at';
            }
            if (Schema::hasColumn('subscriptions', 'paused_at')) {
                $cols[] = 'paused_at';
            }
            if (! empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};
