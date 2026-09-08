<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the hourly wage rate needed for labor-cost / prime-cost reporting on
 * the vendor Financial Reports page. Nullable — a staff member with no rate
 * set simply contributes 0 to labor cost, same "safe on existing rows"
 * pattern already used across this codebase's reporting-field migrations.
 *
 * Set via query-builder `update()`, never through the TeamMember model's
 * mass assignment — this migration intentionally does not touch
 * app/Models/TeamMember.php or any Team Management controller/route.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('team_members', function (Blueprint $table) {
            $table->decimal('hourly_wage', 8, 2)->nullable()->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('team_members', function (Blueprint $table) {
            $table->dropColumn('hourly_wage');
        });
    }
};
