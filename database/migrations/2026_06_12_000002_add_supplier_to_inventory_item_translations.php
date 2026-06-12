<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_item_translations', function (Blueprint $table) {
            $table->string('name')->nullable()->change();
            $table->string('supplier')->nullable()->after('name');
        });

        DB::table('inventory_items')->orderBy('id')->each(function ($item) {
            DB::table('inventory_item_translations')->updateOrInsert(
                ['inventory_item_id' => $item->id, 'language' => 'en'],
                [
                    'name' => $item->name,
                    'supplier' => $item->supplier,
                    'updated_at' => now(),
                ]
            );
        });
    }

    public function down(): void
    {
        DB::table('inventory_item_translations')->whereNull('name')->delete();

        Schema::table('inventory_item_translations', function (Blueprint $table) {
            $table->dropColumn('supplier');
            $table->string('name')->nullable(false)->change();
        });
    }
};
