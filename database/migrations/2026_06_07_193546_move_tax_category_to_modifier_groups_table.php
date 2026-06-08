<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('modifier_groups', function (Blueprint $table) {
            $table->string('tax_category', 64)->nullable()->after('is_required');
        });

        Schema::table('modifier_options', function (Blueprint $table) {
            $table->dropColumn('tax_category');
        });
    }

    public function down(): void
    {
        Schema::table('modifier_options', function (Blueprint $table) {
            $table->string('tax_category', 64)->nullable()->after('price_adjustment');
        });

        Schema::table('modifier_groups', function (Blueprint $table) {
            $table->dropColumn('tax_category');
        });
    }
};
