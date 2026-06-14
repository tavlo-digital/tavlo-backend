<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Promotion extends Model
{
    protected $fillable = [
        'vendor_id',
        'name',
        'type',
        'description',
        'discount_type',
        'discount_value',
        'start_date',
        'end_date',
        'start_time',
        'end_time',
        'active_days',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'discount_value' => 'decimal:2',
            'active_days'    => 'array',
            'is_active'      => 'boolean',
            'start_date'     => 'date',
            'end_date'       => 'date',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }
}
