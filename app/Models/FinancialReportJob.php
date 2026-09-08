<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tracks a background-generated Financial Reports/Analytics payload — see
 * the migration's doc comment for why. `result` holds the exact same JSON
 * shape FinancialReportService::build()/VendorAnalyticsService::build()
 * already return; callers cast it back with `json_decode($job->result, true)`
 * rather than an Eloquent array cast, since a cast would silently
 * re-serialize on every save and this is write-once.
 */
class FinancialReportJob extends Model
{
    public const SOURCE_FINANCIAL_REPORTS = 'financial_reports';

    public const SOURCE_ANALYTICS = 'analytics';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_READY = 'ready';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'vendor_id',
        'source',
        'period_key',
        'period_from',
        'period_to',
        'status',
        'result',
        'error_message',
        'requested_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'period_from' => 'date',
            'period_to' => 'date',
            'requested_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function resultArray(): ?array
    {
        return $this->result !== null ? json_decode($this->result, true) : null;
    }
}
