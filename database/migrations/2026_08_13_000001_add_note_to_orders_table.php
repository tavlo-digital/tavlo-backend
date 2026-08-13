<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('orders', 'note')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->text('note')->nullable()->after('payment_note');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'note')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('note');
            });
        }
    }
};
