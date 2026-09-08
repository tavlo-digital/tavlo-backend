<?php

namespace App\Console\Commands;

use App\Models\FinancialExpense;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes financial-expense receipt files on the private `local` disk that
 * were uploaded via FinancialExpenseController::uploadAttachment() but never
 * ended up referenced by any FinancialExpense row — a vendor who attaches a
 * file to the "add expense" form and then closes the tab without saving
 * leaves that file behind forever, with nothing to ever revisit it
 * (2026-09-08 audit finding). update()/destroy() already clean up files they
 * know about when an attachment is replaced or the expense is deleted; this
 * command is the equivalent sweep for files that were never attached to
 * anything in the first place.
 *
 * A grace period (default 24h) protects a file that was JUST uploaded and is
 * still mid-flow — e.g. the vendor uploaded the receipt and is about to
 * submit the rest of the form — from being deleted out from under them
 * before they ever get the chance to save.
 */
class CleanupOrphanedFinancialAttachments extends Command
{
    protected $signature = 'financials:cleanup-orphaned-attachments
        {--hours=24 : Only delete files last modified at least this many hours ago}';

    protected $description = 'Delete financial-expense attachment files never referenced by any FinancialExpense row';

    public function handle(): int
    {
        $graceHours = max(1, (int) $this->option('hours'));
        $cutoff = now()->subHours($graceHours)->timestamp;
        $disk = Storage::disk('local');

        $referencedPaths = FinancialExpense::query()
            ->whereNotNull('attachment_path')
            ->pluck('attachment_path')
            ->flip();

        $deleted = 0;
        $inspected = 0;

        foreach ($disk->directories('financial-expenses') as $vendorDir) {
            $receiptsDir = "{$vendorDir}/receipts";
            if (! $disk->exists($receiptsDir)) {
                continue;
            }

            foreach ($disk->files($receiptsDir) as $file) {
                $inspected++;

                if ($referencedPaths->has($file)) {
                    continue;
                }

                $lastModified = $disk->lastModified($file);
                if ($lastModified === false || $lastModified > $cutoff) {
                    continue;
                }

                $disk->delete($file);
                $deleted++;
            }
        }

        $this->info("Inspected {$inspected} attachment(s), deleted {$deleted} orphaned file(s) older than {$graceHours}h.");

        return self::SUCCESS;
    }
}
