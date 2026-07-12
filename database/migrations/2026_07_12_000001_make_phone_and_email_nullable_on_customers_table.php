<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Social registrations used to store '' for missing phone/email, which
        // collides with the unique indexes once a second such customer signs up.
        // Columns must become nullable before the '' values can be backfilled.
        Schema::table('customers', function (Blueprint $table) {
            $table->string('phone')->nullable()->change();
            $table->string('email')->nullable()->change();
        });

        DB::table('customers')->where('phone', '')->update(['phone' => null]);
        DB::table('customers')->where('email', '')->update(['email' => null]);
    }

    public function down(): void
    {
        DB::table('customers')->whereNull('phone')->update(['phone' => DB::raw("'legacy-' || id")]);
        DB::table('customers')->whereNull('email')->update(['email' => DB::raw("'legacy-' || id || '@tavlo.invalid'")]);

        Schema::table('customers', function (Blueprint $table) {
            $table->string('phone')->nullable(false)->change();
            $table->string('email')->nullable(false)->change();
        });
    }
};
