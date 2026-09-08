<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `staff_shifts.team_member_id` had a foreign key constraint but no
 * standalone index (2026-09-07 audit finding). MySQL/InnoDB creates one
 * automatically alongside a foreign key; Postgres does not — without this,
 * a `ON DELETE CASCADE` from `team_members` has to full-scan `staff_shifts`
 * to find the rows to remove on that engine, and any future per-staff-member
 * query (a shift history view, e.g.) would too. No current query filters by
 * `team_member_id` alone yet — this is forward-looking hygiene, not a fix
 * for an active slow query.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_shifts', function (Blueprint $table) {
            $table->index('team_member_id');
        });
    }

    public function down(): void
    {
        Schema::table('staff_shifts', function (Blueprint $table) {
            $table->dropIndex(['team_member_id']);
        });
    }
};
