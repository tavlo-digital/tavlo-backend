<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Friendly order number per vendor (e.g. #9001)
            $table->unsignedBigInteger('order_number')->nullable()->after('order_public_id');

            // Dine-in or takeaway
            $table->string('order_type')->default('dine-in')->after('order_number'); // dine-in | takeaway

            // Table reference (denormalised for quick display)
            $table->string('table_number')->nullable()->after('order_type');

            // Service fee and VAT breakdown
            $table->decimal('service_fee', 10, 2)->default(0)->after('amount');
            $table->decimal('vat_amount', 10, 2)->default(0)->after('service_fee');

            // Course tracking for this order
            $table->string('course')->nullable()->after('vat_amount'); // drinks|appetizers|mains|desserts

            // Items array already added in previous migration (2026_03_28_000006)
            // Adding guest count
            $table->unsignedInteger('guest_count')->default(1)->after('course');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'order_number',
                'order_type',
                'table_number',
                'service_fee',
                'vat_amount',
                'course',
                'guest_count',
            ]);
        });
    }
};
