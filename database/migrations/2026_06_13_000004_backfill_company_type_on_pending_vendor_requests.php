<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('vendor_request_changes')
            ->where('status', 'pending')
            ->whereNull('company_type')
            ->orderBy('id')
            ->eachById(function ($change): void {
                $companyType = DB::table('vendor_settings')
                    ->where('vendor_id', $change->vendor_id)
                    ->value('company_type');

                if ($companyType !== null) {
                    DB::table('vendor_request_changes')
                        ->where('id', $change->id)
                        ->update(['company_type' => $companyType]);
                }
            });
    }

    public function down(): void
    {
        // Existing pending values cannot be distinguished from backfilled values.
    }
};
