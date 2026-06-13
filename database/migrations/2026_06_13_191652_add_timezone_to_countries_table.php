<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('countries', function (Blueprint $table) {
            $table->string('timezone', 50)->default('UTC')->after('currency');
        });

        $timezones = [
            'AT' => 'Europe/Vienna',
            'DE' => 'Europe/Berlin',
            'GB' => 'Europe/London',
        ];

        foreach ($timezones as $code => $tz) {
            DB::table('countries')->where('code', $code)->update(['timezone' => $tz]);
        }
    }

    public function down(): void
    {
        Schema::table('countries', function (Blueprint $table) {
            $table->dropColumn('timezone');
        });
    }
};
