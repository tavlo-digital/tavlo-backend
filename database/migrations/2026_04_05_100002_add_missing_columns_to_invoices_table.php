<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('invoices', 'vat')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->decimal('vat', 10, 2)->default(0.00)->after('amount');
            });
        }

        if (! Schema::hasColumn('invoices', 'pdf_url')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->string('pdf_url')->nullable()->after('paid_at');
            });
        }

        if (! Schema::hasColumn('invoices', 'stripe_invoice_id')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->string('stripe_invoice_id')->nullable()->after('pdf_url');
            });
        }

        if (! Schema::hasColumn('invoices', 'stripe_hosted_url')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->string('stripe_hosted_url')->nullable()->after('stripe_invoice_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $cols = [];
            foreach (['vat', 'pdf_url', 'stripe_invoice_id', 'stripe_hosted_url'] as $col) {
                if (Schema::hasColumn('invoices', $col)) {
                    $cols[] = $col;
                }
            }
            if (! empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};
