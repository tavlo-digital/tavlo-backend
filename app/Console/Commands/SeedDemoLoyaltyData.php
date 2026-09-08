<?php

namespace App\Console\Commands;

use App\Models\CustomerLoyaltyPoint;
use App\Models\LoyaltyTransaction;
use App\Models\Order;
use App\Models\Vendor;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Seeds demo loyalty point wallets + matching transaction history so the
 * Financial Reports "Loyalty points liability" card and Analytics' Loyalty
 * section have something to show. Nothing in the order/payment flow ever
 * writes to customer_loyalty_points or loyalty_transactions yet — until a
 * real points-earning feature ships, this is the only source for either
 * table's rows.
 *
 * Every row this command writes is tagged `source = 'demo'`, so it is
 * always programmatically distinguishable from real activity and the
 * frontend can (and does) flag it to the vendor rather than presenting it
 * as measured fact — same convention as `SeedDemoLaborData` /
 * `staff_shifts.source`.
 *
 * Wallets are attached to real customers who have actually ordered from the
 * vendor (never synthetic customer rows). Each wallet's `total_earned` /
 * `total_redeemed` is broken into several backdated `loyalty_transactions`
 * rows spread across `--days`, and the wallet itself joins mid-history
 * rather than "now" — so period-scoped figures (pts earned/redeemed this
 * period, new members this period) vary realistically by the selected
 * report window instead of dumping everything into whatever period happens
 * to include today.
 */
class SeedDemoLoyaltyData extends Command
{
    protected $signature = 'financials:seed-demo-loyalty
        {--vendor= : Vendor id or vendor_public_id to seed (default: every vendor with loyalty enabled)}
        {--days=90 : How many days of wallet/transaction history to generate}
        {--force : Delete and regenerate existing demo wallets and transactions for the vendor}
        {--force-production : Required IN ADDITION to any other flag to run this command when APP_ENV=production}';

    protected $description = 'Seed demo loyalty wallets and transaction history for the loyalty liability report (no real points-earning feature exists yet)';

    public function handle(): int
    {
        // Every row this command writes is already tagged source='demo' and
        // it already refuses to touch a vendor with real wallets — lower
        // risk than SeedDemoLaborData's untagged hourly_wage write — but it
        // still runs unattended against any matching vendor with no
        // environment gate at all, same underlying gap (2026-09-08 audit
        // finding). Guarded the same way for consistency.
        if (app()->environment('production') && ! $this->option('force-production')) {
            $this->error('This command writes fabricated demo loyalty data and is disabled in production. Re-run with --force-production only if you are certain that is what you want.');

            return self::FAILURE;
        }

        $days = max(1, (int) $this->option('days'));
        $force = (bool) $this->option('force');

        $vendors = $this->resolveVendors();

        if ($vendors->isEmpty()) {
            $this->warn('No vendors with loyalty enabled found.');

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

        $query = Vendor::query()->whereHas('vendorSetting', fn ($q) => $q->where('loyalty_enabled', true));

        if ($option) {
            $query->where(fn ($q) => $q->where('id', $option)->orWhere('vendor_public_id', $option));
        }

        return $query->get();
    }

    private function seedForVendor(Vendor $vendor, int $days, bool $force): void
    {
        $hasRealWallets = CustomerLoyaltyPoint::where('vendor_id', $vendor->id)->where('source', '!=', 'demo')->exists();

        if ($hasRealWallets) {
            $this->line("Vendor {$vendor->id} ({$vendor->name}): real loyalty wallets already exist, skipping.");

            return;
        }

        $hasDemoWallets = CustomerLoyaltyPoint::where('vendor_id', $vendor->id)->where('source', 'demo')->exists();

        if ($hasDemoWallets && ! $force) {
            $this->line("Vendor {$vendor->id} ({$vendor->name}): demo wallets already exist, skipping (pass --force to regenerate).");

            return;
        }

        if ($force) {
            $demoCustomerIds = CustomerLoyaltyPoint::where('vendor_id', $vendor->id)->where('source', 'demo')->pluck('customer_id');
            LoyaltyTransaction::where('vendor_id', $vendor->id)->whereIn('customer_id', $demoCustomerIds)->where('source', 'demo')->delete();
            CustomerLoyaltyPoint::where('vendor_id', $vendor->id)->where('source', 'demo')->delete();
        }

        $customerIds = Order::where('vendor_id', $vendor->id)
            ->whereNotNull('customer_id')
            ->distinct()
            ->pluck('customer_id');

        if ($customerIds->isEmpty()) {
            $this->line("Vendor {$vendor->id} ({$vendor->name}): no customers have ordered yet, skipping.");

            return;
        }

        $minRedemption = 100;
        $now = Carbon::now();
        $walletRows = [];
        $transactionRows = [];

        foreach ($customerIds as $customerId) {
            // Not every past customer joined the loyalty program.
            if (mt_rand(1, 100) > 70) {
                continue;
            }

            $totalEarned = mt_rand(150, 2400);
            $totalRedeemed = (int) round($totalEarned * (mt_rand(20, 70) / 100));
            $totalRedeemed -= $totalRedeemed % $minRedemption;
            $balance = max(0, $totalEarned - $totalRedeemed);

            $joinedAt = $now->copy()->subDays(mt_rand(0, $days))->setTime(mt_rand(8, 20), mt_rand(0, 59));

            $walletRows[] = [
                'customer_id' => $customerId,
                'vendor_id' => $vendor->id,
                'points_balance' => $balance,
                'total_earned' => $totalEarned,
                'total_redeemed' => $totalRedeemed,
                'source' => 'demo',
                'created_at' => $joinedAt->toDateTimeString(),
                'updated_at' => $now->toDateTimeString(),
            ];

            $earnAmounts = $this->splitIntoParts($totalEarned, mt_rand(3, 8));
            $earnDates = $this->spreadDates($joinedAt, $now, count($earnAmounts));

            foreach ($earnAmounts as $i => $amount) {
                $transactionRows[] = $this->transactionRow($customerId, $vendor->id, 'earned', $amount, $earnDates[$i]);
            }

            if ($totalRedeemed > 0 && ! empty($earnDates)) {
                // Redemptions only happen after the member has actually
                // earned something — never before the first earn event.
                $redeemAmounts = $this->splitIntoParts($totalRedeemed, mt_rand(1, 3));
                $redeemWindowStart = $earnDates[0];
                $redeemDates = $this->spreadDates($redeemWindowStart, $now, count($redeemAmounts));

                foreach ($redeemAmounts as $i => $amount) {
                    $transactionRows[] = $this->transactionRow($customerId, $vendor->id, 'redeemed', -$amount, $redeemDates[$i]);
                }
            }
        }

        if (empty($walletRows)) {
            $this->line("Vendor {$vendor->id} ({$vendor->name}): no wallets generated this run (random skip).");

            return;
        }

        foreach (array_chunk($walletRows, 200) as $chunk) {
            CustomerLoyaltyPoint::insert($chunk);
        }

        foreach (array_chunk($transactionRows, 200) as $chunk) {
            LoyaltyTransaction::insert($chunk);
        }

        $this->info("Vendor {$vendor->id} ({$vendor->name}): seeded ".count($walletRows).' demo loyalty wallets and '.count($transactionRows).' transactions.');
    }

    /** @return array<int, array{customer_id:int,vendor_id:int,type:string,points:int,description:string,source:string,created_at:string,updated_at:string}> */
    private function transactionRow(int $customerId, int $vendorId, string $type, int $points, Carbon $at): array
    {
        return [
            'customer_id' => $customerId,
            'vendor_id' => $vendorId,
            'type' => $type,
            'points' => $points,
            'reference_type' => null,
            'reference_id' => null,
            'description' => 'Demo seed data',
            'source' => 'demo',
            'created_at' => $at->toDateTimeString(),
            'updated_at' => $at->toDateTimeString(),
        ];
    }

    /**
     * Splits $total into $parts positive integers that sum exactly to
     * $total, weighted randomly rather than evenly, so a wallet's history
     * reads as several plausibly-sized visits, not $total/$parts repeated.
     *
     * @return array<int, int>
     */
    private function splitIntoParts(int $total, int $parts): array
    {
        if ($total <= 0 || $parts <= 0) {
            return [];
        }

        $parts = min($parts, $total);
        $weights = array_map(fn () => mt_rand(1, 100), range(1, $parts));
        $weightSum = array_sum($weights);
        $amounts = array_map(fn ($w) => (int) floor($total * $w / $weightSum), $weights);

        $remainder = $total - array_sum($amounts);
        for ($i = 0; $i < $remainder; $i++) {
            $amounts[$i % $parts]++;
        }

        return array_values(array_filter($amounts, fn ($a) => $a > 0));
    }

    /**
     * $count dates spread randomly across [$from, $to], sorted chronologically.
     *
     * @return array<int, Carbon>
     */
    private function spreadDates(Carbon $from, Carbon $to, int $count): array
    {
        if ($count <= 0) {
            return [];
        }

        $rangeDays = max(0, $from->diffInDays($to));

        $dates = [];
        for ($i = 0; $i < $count; $i++) {
            $offset = $rangeDays > 0 ? mt_rand(0, $rangeDays) : 0;
            $dates[] = $from->copy()->addDays($offset)->setTime(mt_rand(8, 20), mt_rand(0, 59));
        }

        usort($dates, fn (Carbon $a, Carbon $b) => $a <=> $b);

        return $dates;
    }
}
