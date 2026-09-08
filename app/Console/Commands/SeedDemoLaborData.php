<?php

namespace App\Console\Commands;

use App\Models\StaffShift;
use App\Models\TeamMember;
use App\Models\Vendor;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Seeds demo hourly wages + shift history so the Financial Reports page's
 * Labor Cost / Prime Cost section has something to show. No real clock-in
 * feature exists anywhere in this codebase — until one does, this is the
 * only source for `staff_shifts` rows.
 *
 * Every row this command writes is tagged `source = 'demo'`, so it is
 * always programmatically distinguishable from a real shift and the
 * frontend can (and does) flag it to the vendor rather than presenting it
 * as measured fact.
 */
class SeedDemoLaborData extends Command
{
    protected $signature = 'financials:seed-demo-labor
        {--vendor= : Vendor id or vendor_public_id to seed (default: every vendor with active team members)}
        {--days=90 : How many days of shift history to generate}
        {--force : Delete and regenerate existing demo shifts for the vendor}
        {--force-production : Required IN ADDITION to any other flag to run this command when APP_ENV=production}';

    protected $description = 'Seed demo hourly wages and shift history for labor-cost/prime-cost reporting (no real timesheet feature exists yet)';

    /** [min, max] hourly rate per role, in the vendor's local currency units. */
    private const ROLE_WAGE_RANGE = [
        'manager' => [22.0, 28.0],
        'kitchen' => [15.0, 19.0],
        'waiter' => [13.0, 16.0],
    ];

    public function handle(): int
    {
        // This command writes a fabricated hourly_wage onto real, live
        // team_members rows (see seedForVendor() below) with no tag
        // distinguishing it from a real admin-entered rate — nothing
        // downstream can tell the difference. Nothing in this app schedules
        // or routes to this command automatically, but there was previously
        // no guard at all stopping an operator, deploy script, or
        // copy-pasted runbook step from running it directly against
        // production and silently corrupting real staff pay data
        // (2026-09-08 audit finding).
        if (app()->environment('production') && ! $this->option('force-production')) {
            $this->error('This command writes fabricated wage/shift data — including directly onto real team_members.hourly_wage rows with no tag marking it as fake — and is disabled in production. Re-run with --force-production only if you are certain that is what you want.');

            return self::FAILURE;
        }

        $days = max(1, (int) $this->option('days'));
        $force = (bool) $this->option('force');

        $vendors = $this->resolveVendors();

        if ($vendors->isEmpty()) {
            $this->warn('No vendors with active team members found.');

            return self::SUCCESS;
        }

        foreach ($vendors as $vendor) {
            $this->seedForVendor($vendor, $days, $force);
        }

        $this->info('Done.');

        return self::SUCCESS;
    }

    /** @return Collection<int, Vendor> */
    private function resolveVendors(): Collection
    {
        $option = $this->option('vendor');

        $query = Vendor::query()->whereHas('teamMembers', fn ($q) => $q->where('status', 'active'));

        if ($option) {
            $query->where(fn ($q) => $q->where('id', $option)->orWhere('vendor_public_id', $option));
        }

        return $query->get();
    }

    private function seedForVendor(Vendor $vendor, int $days, bool $force): void
    {
        $members = TeamMember::where('vendor_id', $vendor->id)->where('status', 'active')->get();

        if ($members->isEmpty()) {
            return;
        }

        $hasDemoShifts = StaffShift::where('vendor_id', $vendor->id)->where('source', 'demo')->exists();

        if ($hasDemoShifts && ! $force) {
            $this->line("Vendor {$vendor->id} ({$vendor->name}): demo shifts already exist, skipping (pass --force to regenerate).");

            return;
        }

        if ($force) {
            StaffShift::where('vendor_id', $vendor->id)->where('source', 'demo')->delete();
        }

        foreach ($members as $member) {
            if ($member->hourly_wage !== null) {
                continue;
            }

            [$min, $max] = self::ROLE_WAGE_RANGE[$member->role] ?? [14.0, 18.0];
            $rate = round($min + (mt_rand(0, 100) / 100) * ($max - $min), 2);

            TeamMember::where('id', $member->id)->update(['hourly_wage' => $rate]);
            $member->hourly_wage = $rate;
        }

        $timezone = $vendor->resolveTimezone();
        $rows = [];
        $now = Carbon::now();

        for ($dayOffset = 0; $dayOffset < $days; $dayOffset++) {
            $date = Carbon::now($timezone)->subDays($dayOffset)->startOfDay();

            foreach ($members as $member) {
                // Not every staff member works every day.
                if (mt_rand(1, 100) > 60) {
                    continue;
                }

                $startHour = $member->role === 'kitchen' ? mt_rand(9, 11) : mt_rand(11, 17);
                $startMinute = [0, 15, 30, 45][mt_rand(0, 3)];
                $shiftHours = mt_rand(4, 8);

                $clockIn = $date->copy()->setTime($startHour, $startMinute);
                $clockOut = $clockIn->copy()->addHours($shiftHours)->addMinutes(mt_rand(0, 45));

                $rows[] = [
                    'vendor_id' => $vendor->id,
                    'team_member_id' => $member->id,
                    'clock_in_at' => $clockIn->toDateTimeString(),
                    'clock_out_at' => $clockOut->toDateTimeString(),
                    'hourly_rate' => $member->hourly_wage,
                    'source' => 'demo',
                    'created_at' => $now->toDateTimeString(),
                    'updated_at' => $now->toDateTimeString(),
                ];
            }
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            StaffShift::insert($chunk);
        }

        $this->info("Vendor {$vendor->id} ({$vendor->name}): seeded ".count($rows)." demo shifts across {$members->count()} staff.");
    }
}
