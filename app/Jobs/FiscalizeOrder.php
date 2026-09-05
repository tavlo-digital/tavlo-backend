<?php

namespace App\Jobs;

use App\Models\FiscalReceipt;
use App\Services\Fiscal\FiscalizationService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Signs one receipt at fiskaly.
 *
 * Unique per receipt so a duplicated dispatch cannot start a second signing,
 * and retried with a widening backoff because the failure this most often has
 * to survive is a brief provider outage.
 */
class FiscalizeOrder implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 60;

    public function __construct(public readonly int $fiscalReceiptId)
    {
        $this->onConnection((string) config('services.fiskaly.queue_connection'))
            ->onQueue((string) config('services.fiskaly.queue', 'fiscal'));
    }

    public function uniqueId(): string
    {
        return 'fiscalize-receipt-'.$this->fiscalReceiptId;
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [10, 60, 300, 900];
    }

    public function handle(FiscalizationService $fiscalization): void
    {
        $receipt = FiscalReceipt::find($this->fiscalReceiptId);

        if (! $receipt || $receipt->isSigned()) {
            return;
        }

        $fiscalization->sign($receipt);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Fiscalization failed after every retry.', [
            'fiscal_receipt_id' => $this->fiscalReceiptId,
            'exception' => $exception,
        ]);
    }
}
