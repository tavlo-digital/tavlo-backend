<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('vendor_settings', 'accept_on_site')) {
            Schema::table('vendor_settings', function (Blueprint $table) {
                $table->boolean('accept_on_site')->default(true);
            });
        }

        $hasAcceptCash = Schema::hasColumn('vendor_settings', 'accept_cash');
        $hasAcceptCard = Schema::hasColumn('vendor_settings', 'accept_card');

        if ($hasAcceptCash || $hasAcceptCard) {
            DB::table('vendor_settings')
                ->select(array_values(array_filter([
                    'id',
                    $hasAcceptCash ? 'accept_cash' : null,
                    $hasAcceptCard ? 'accept_card' : null,
                ])))
                ->orderBy('id')
                ->chunkById(100, function ($settings) use ($hasAcceptCash, $hasAcceptCard) {
                    foreach ($settings as $setting) {
                        $acceptCash = $hasAcceptCash ? (bool) $setting->accept_cash : false;
                        $acceptCard = $hasAcceptCard ? (bool) $setting->accept_card : false;

                        DB::table('vendor_settings')
                            ->where('id', $setting->id)
                            ->update([
                                'accept_on_site' => $acceptCash || $acceptCard,
                            ]);
                    }
                });
        }

        $columns = array_values(array_filter([
            Schema::hasColumn('vendor_settings', 'payment_collection_model') ? 'payment_collection_model' : null,
            Schema::hasColumn('vendor_settings', 'accept_cash') ? 'accept_cash' : null,
            Schema::hasColumn('vendor_settings', 'accept_cash_takeaway') ? 'accept_cash_takeaway' : null,
            Schema::hasColumn('vendor_settings', 'accept_card') ? 'accept_card' : null,
            Schema::hasColumn('vendor_settings', 'accept_apple_pay') ? 'accept_apple_pay' : null,
            Schema::hasColumn('vendor_settings', 'accept_google_pay') ? 'accept_google_pay' : null,
            Schema::hasColumn('vendor_settings', 'accept_visa') ? 'accept_visa' : null,
            Schema::hasColumn('vendor_settings', 'accept_mastercard') ? 'accept_mastercard' : null,
            Schema::hasColumn('vendor_settings', 'accept_amex') ? 'accept_amex' : null,
            Schema::hasColumn('vendor_settings', 'accept_bank_transfer') ? 'accept_bank_transfer' : null,
        ]));

        if ($columns !== []) {
            Schema::table('vendor_settings', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }

    public function down(): void
    {
        Schema::table('vendor_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('vendor_settings', 'payment_collection_model')) {
                $table->string('payment_collection_model')->default('on-site');
            }
            if (! Schema::hasColumn('vendor_settings', 'accept_cash')) {
                $table->boolean('accept_cash')->default(true);
            }
            if (! Schema::hasColumn('vendor_settings', 'accept_cash_takeaway')) {
                $table->boolean('accept_cash_takeaway')->default(true);
            }
            if (! Schema::hasColumn('vendor_settings', 'accept_card')) {
                $table->boolean('accept_card')->default(true);
            }
            if (! Schema::hasColumn('vendor_settings', 'accept_apple_pay')) {
                $table->boolean('accept_apple_pay')->default(false);
            }
            if (! Schema::hasColumn('vendor_settings', 'accept_google_pay')) {
                $table->boolean('accept_google_pay')->default(false);
            }
            if (! Schema::hasColumn('vendor_settings', 'accept_visa')) {
                $table->boolean('accept_visa')->default(true);
            }
            if (! Schema::hasColumn('vendor_settings', 'accept_mastercard')) {
                $table->boolean('accept_mastercard')->default(true);
            }
            if (! Schema::hasColumn('vendor_settings', 'accept_amex')) {
                $table->boolean('accept_amex')->default(false);
            }
            if (! Schema::hasColumn('vendor_settings', 'accept_bank_transfer')) {
                $table->boolean('accept_bank_transfer')->default(false);
            }
        });

        if (Schema::hasColumn('vendor_settings', 'accept_on_site')) {
            DB::table('vendor_settings')
                ->select('id', 'accept_on_site')
                ->orderBy('id')
                ->chunkById(100, function ($settings) {
                    foreach ($settings as $setting) {
                        DB::table('vendor_settings')
                            ->where('id', $setting->id)
                            ->update([
                                'accept_cash' => (bool) $setting->accept_on_site,
                                'accept_cash_takeaway' => (bool) $setting->accept_on_site,
                                'accept_card' => (bool) $setting->accept_on_site,
                            ]);
                    }
                });

            Schema::table('vendor_settings', function (Blueprint $table) {
                $table->dropColumn('accept_on_site');
            });
        }
    }
};
