<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Review extends Model
{
    protected $fillable = [
        'review_public_id',
        'customer_id',
        'vendor_id',
        'order_id',
        'table_scan_session_id',
        'rating',
        'text',
        'images',
        'vendor_reply',
        'vendor_replied_at',
        'flagged',
        'flag_reason',
        'anonymous',
    ];

    protected function casts(): array
    {
        return [
            'flagged' => 'boolean',
            'anonymous' => 'boolean',
            'vendor_replied_at' => 'datetime',
            'images' => 'array',
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

    public function tableScanSession(): BelongsTo
    {
        return $this->belongsTo(TableScanSession::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReviewItem::class);
    }
}
