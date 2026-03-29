<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    protected $fillable = [
        'review_public_id',
        'customer_id',
        'vendor_id',
        'order_id',
        'rating',
        'text',
        'vendor_reply',
        'vendor_replied_at',
        'flagged',
        'flag_reason',
    ];

    protected function casts(): array
    {
        return [
            'flagged' => 'boolean',
            'vendor_replied_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
