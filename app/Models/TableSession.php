<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TableSession extends Model
{
    use HasFactory;

    protected $table = 'table_sessions';

    protected $fillable = [
        'vendor_id',
        'table_number',
        'table_name',
        'status',
        'batch_started_at',
        'batch_window_seconds',
        'batch_released_at',
        'current_course',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'batch_started_at'  => 'datetime',
            'batch_released_at' => 'datetime',
            'closed_at'         => 'datetime',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Ordered course sequence used for "fire next course" logic.
     */
    public static function courseSequence(): array
    {
        return ['drinks', 'appetizers', 'mains', 'desserts'];
    }

    /**
     * Return the next course after the current one, or null if already on desserts.
     */
    public function nextCourse(): ?string
    {
        $sequence = self::courseSequence();
        $index    = array_search($this->current_course, $sequence, true);

        if ($index === false) {
            return $sequence[0]; // start from drinks if not set
        }

        return $sequence[$index + 1] ?? null;
    }

    /**
     * Total amount across all non-cancelled orders.
     */
    public function totalAmount(): float
    {
        return (float) $this->orders()
            ->where('status', '!=', 'cancelled')
            ->sum('amount');
    }

    /**
     * Whether the batch window is still open.
     */
    public function isBatchOpen(): bool
    {
        if (! $this->batch_started_at || $this->batch_released_at) {
            return false;
        }

        return now()->diffInSeconds($this->batch_started_at, false) < $this->batch_window_seconds;
    }

    /**
     * Seconds remaining in the batch window, 0 if elapsed or already released.
     */
    public function batchSecondsRemaining(): int
    {
        if (! $this->isBatchOpen()) {
            return 0;
        }

        $elapsed = (int) now()->diffInSeconds($this->batch_started_at, false);
        return max(0, $this->batch_window_seconds - $elapsed);
    }
}
