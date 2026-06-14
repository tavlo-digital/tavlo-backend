<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_public_id',
        'invoice_number',
        'customer_id',
        'vendor_id',
        'status',
        'amount',
        'currency',
        'tip_amount',
        'payment_method',
        'transaction_id',
        'payment_pending',
        'payment_received',
        'payment_confirmed_at',
        'payment_note',
        // extended fields
        'order_number',
        'order_type',
        'table_number',
        'service_fee',
        'vat_amount',
        'course',
        'served_at',
        'cancelled_at',
        'cancelled_reason',
        // session fields
        'table_scan_session_id',
        'waiter_confirmed',
        'waiter_confirmed_at',
        // loyalty & promotions
        'loyalty_points_redeemed',
        'loyalty_discount',
        'promotion_id',
        'promotion_discount',
    ];

    protected function casts(): array
    {
        return [
            'amount'               => 'decimal:2',
            'tip_amount'           => 'decimal:2',
            'service_fee'          => 'decimal:2',
            'vat_amount'           => 'decimal:2',
            'payment_pending'      => 'boolean',
            'payment_received'     => 'boolean',
            'payment_confirmed_at' => 'datetime',
            'served_at'            => 'datetime',
            'cancelled_at'         => 'datetime',
            'waiter_confirmed'     => 'boolean',
            'waiter_confirmed_at'  => 'datetime',
            'loyalty_discount'     => 'decimal:2',
            'promotion_discount'   => 'decimal:2',
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

    public function tableScanSession(): BelongsTo
    {
        return $this->belongsTo(TableScanSession::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(OrderPayment::class);
    }
}
