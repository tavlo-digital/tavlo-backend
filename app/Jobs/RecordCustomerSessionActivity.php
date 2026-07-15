<?php

namespace App\Jobs;

use App\Models\CustomerSessionActivity;
use App\Models\TableScanSession;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class RecordCustomerSessionActivity implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(
        public readonly int $customerId,
        public readonly string $endpoint,
        public readonly string $method,
        public readonly string $occurredAt,
    ) {
        $this->onQueue((string) config('services.session_activity.queue', 'activity'));
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [1, 5, 15];
    }

    public function handle(): void
    {
        $occurredAt = CarbonImmutable::parse($this->occurredAt);
        $session = TableScanSession::query()
            ->where('customer_id', $this->customerId)
            ->where('scanned_at', '<=', $occurredAt)
            ->where(function ($query) use ($occurredAt): void {
                $query->where('status', 'active')
                    ->orWhere('closed_at', '>=', $occurredAt);
            })
            ->latest('scanned_at')
            ->first(['id']);

        if (! $session) {
            return;
        }

        $activity = new CustomerSessionActivity([
            'table_scan_session_id' => $session->id,
            'customer_id' => $this->customerId,
            'endpoint' => $this->endpoint,
            'method' => $this->method,
        ]);
        $activity->created_at = $occurredAt;
        $activity->updated_at = $occurredAt;
        $activity->save();
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Customer session activity job failed.', [
            'customer_id' => $this->customerId,
            'endpoint' => $this->endpoint,
            'occurred_at' => $this->occurredAt,
            'exception' => $exception,
        ]);
    }
}
