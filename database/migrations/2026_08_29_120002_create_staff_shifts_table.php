<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * New table backing labor-cost / prime-cost reporting on the vendor
 * Financial Reports page. Nothing like this existed anywhere in the
 * codebase before — no timesheet, wage, or shift table of any kind.
 *
 * `hourly_rate` is snapshotted per shift (not read live from
 * team_members.hourly_wage at report time) — standard payroll practice:
 * if a staff member's rate changes, historical shifts must keep reflecting
 * the rate they were actually paid at the time, the same reasoning that
 * makes real payroll systems store a rate per pay period rather than a
 * single current rate on the employee record.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_member_id')->constrained()->cascadeOnDelete();
            $table->timestamp('clock_in_at');
            $table->timestamp('clock_out_at')->nullable();
            $table->decimal('hourly_rate', 8, 2);
            // 'manual' for a real clocked shift, 'demo' for seeded sample
            // data (see App\Console\Commands\SeedDemoLaborData) — always
            // distinguishable from real entries, never silently mixed in.
            $table->string('source', 20)->default('manual');
            $table->timestamps();

            $table->index(['vendor_id', 'clock_in_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_shifts');
    }
};
