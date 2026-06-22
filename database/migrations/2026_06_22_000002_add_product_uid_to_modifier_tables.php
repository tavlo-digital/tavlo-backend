<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('modifier_groups', function (Blueprint $table) {
            $table->uuid('product_uid')->nullable()->after('id');
        });

        DB::table('modifier_groups')->whereNull('product_uid')->eachById(function ($row) {
            DB::table('modifier_groups')->where('id', $row->id)->update(['product_uid' => (string) Str::uuid()]);
        });

        Schema::table('modifier_groups', function (Blueprint $table) {
            $table->uuid('product_uid')->nullable(false)->change();
            $table->index('product_uid');
        });

        Schema::table('modifier_options', function (Blueprint $table) {
            $table->uuid('product_uid')->nullable()->after('id');
        });

        DB::table('modifier_options')->whereNull('product_uid')->eachById(function ($row) {
            DB::table('modifier_options')->where('id', $row->id)->update(['product_uid' => (string) Str::uuid()]);
        });

        Schema::table('modifier_options', function (Blueprint $table) {
            $table->uuid('product_uid')->nullable(false)->change();
            $table->index('product_uid');
        });
    }

    public function down(): void
    {
        Schema::table('modifier_options', function (Blueprint $table) {
            $table->dropIndex(['product_uid']);
            $table->dropColumn('product_uid');
        });

        Schema::table('modifier_groups', function (Blueprint $table) {
            $table->dropIndex(['product_uid']);
            $table->dropColumn('product_uid');
        });
    }
};
