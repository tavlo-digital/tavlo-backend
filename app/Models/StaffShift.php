<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffShift extends Model
{
    protected $fillable = [
        'vendor_id',
        'team_member_id',
        'clock_in_at',
        'clock_out_at',
        'hourly_rate',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'clock_in_at' => 'datetime',
            'clock_out_at' => 'datetime',
            'hourly_rate' => 'decimal:2',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function teamMember(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class);
    }

    /** Hours worked, or 0 for a shift that hasn't clocked out yet. */
    public function hours(): float
    {
        if (! $this->clock_out_at) {
            return 0.0;
        }

        return round($this->clock_in_at->diffInMinutes($this->clock_out_at) / 60, 2);
    }

    public function cost(): float
    {
        return round($this->hours() * (float) $this->hourly_rate, 2);
    }
}
