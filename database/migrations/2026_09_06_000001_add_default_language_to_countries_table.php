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
            // The language a receipt is written in. A receipt is a legal
            // document of the restaurant, so it follows the country the
            // restaurant is registered in rather than whatever language the
            // diner's browser happens to ask for.
            $table->string('default_language', 10)->default('en')->after('currency');
        });

        foreach (['AT' => 'de', 'DE' => 'de', 'GB' => 'en'] as $code => $language) {
            DB::table('countries')->where('code', $code)->update(['default_language' => $language]);
        }
    }

    public function down(): void
    {
        Schema::table('countries', function (Blueprint $table) {
            $table->dropColumn('default_language');
        });
    }
};
