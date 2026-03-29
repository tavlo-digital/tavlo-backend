<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    protected $fillable = [
        'order_public_id',
        'customer_id',
        'vendor_id',
        'status',
        'items_count',
        'items',
        'amount',
        'currency',
        'payment_method',
        'transaction_id',
        'payment_pending',
        'payment_received',
        'payment_confirmed_at',
        'payment_note',
        'ready_at',
        'picked_up_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'items' => 'array',
            'payment_pending' => 'boolean',
            'payment_received' => 'boolean',
            'payment_confirmed_at' => 'datetime',
            'ready_at' => 'datetime',
            'picked_up_at' => 'datetime',
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
}
