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
        Schema::table('menu_items', function (Blueprint $table) {
            $table->softDeletes();
            $table->uuid('product_uid')->nullable()->after('id');
        });

        DB::table('menu_items')->whereNull('product_uid')->eachById(function ($item) {
            DB::table('menu_items')
                ->where('id', $item->id)
                ->update(['product_uid' => (string) Str::uuid()]);
        });

        Schema::table('menu_items', function (Blueprint $table) {
            $table->uuid('product_uid')->nullable(false)->change();
            $table->index('product_uid');
        });

        Schema::table('modifier_groups', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('modifier_options', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropForeign(['menu_item_id']);
            $table->unsignedBigInteger('menu_item_id')->nullable()->change();
            $table->foreign('menu_item_id')->references('id')->on('menu_items')->nullOnDelete();
        });

        if (Schema::hasTable('review_items') && Schema::hasColumn('review_items', 'menu_item_id')) {
            Schema::table('review_items', function (Blueprint $table) {
                $table->dropForeign(['menu_item_id']);
                $table->unsignedBigInteger('menu_item_id')->nullable()->change();
                $table->foreign('menu_item_id')->references('id')->on('menu_items')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('review_items') && Schema::hasColumn('review_items', 'menu_item_id')) {
            Schema::table('review_items', function (Blueprint $table) {
                $table->dropForeign(['menu_item_id']);
                $table->unsignedBigInteger('menu_item_id')->nullable(false)->change();
                $table->foreign('menu_item_id')->references('id')->on('menu_items')->cascadeOnDelete();
            });
        }

        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropForeign(['menu_item_id']);
            $table->unsignedBigInteger('menu_item_id')->nullable(false)->change();
            $table->foreign('menu_item_id')->references('id')->on('menu_items')->cascadeOnDelete();
        });

        Schema::table('modifier_options', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('modifier_groups', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropIndex(['product_uid']);
            $table->dropColumn('product_uid');
            $table->dropSoftDeletes();
        });
    }
};
