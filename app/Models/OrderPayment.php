<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class OrderPayment extends Model
{
    protected $fillable = [
        'order_id',
        'vendor_id',
        'customer_id',
        'table_scan_session_id',
        'stripe_account_id',
        'stripe_payment_intent_id',
        'amount',
        'currency',
        'status',
        'payment_method',
        'metadata',
        'paid_at',
        'failed_at',
        'last_verified_at',
    ];

    protected function casts(): array
    {
        return [
            'amount'           => 'decimal:2',
            'metadata'         => 'array',
            'paid_at'          => 'datetime',
            'failed_at'        => 'datetime',
            'last_verified_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orders(): BelongsToMany
    {
        return $this->belongsToMany(Order::class, 'order_payment_orders')
            ->withPivot('amount')
            ->withTimestamps();
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function tableScanSession(): BelongsTo
    {
        return $this->belongsTo(TableScanSession::class);
    }
}
