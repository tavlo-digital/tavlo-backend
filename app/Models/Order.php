<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    use HasFactory;

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
        // extended fields
        'order_number',
        'order_type',
        'table_number',
        'service_fee',
        'vat_amount',
        'course',
        'guest_count',
        'served_at',
        'cancelled_at',
        'cancelled_reason',
        // session fields
        'table_session_id',
        'waiter_confirmed',
        'waiter_confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount'               => 'decimal:2',
            'service_fee'          => 'decimal:2',
            'vat_amount'           => 'decimal:2',
            'items'                => 'array',
            'payment_pending'      => 'boolean',
            'payment_received'     => 'boolean',
            'payment_confirmed_at' => 'datetime',
            'ready_at'             => 'datetime',
            'picked_up_at'         => 'datetime',
            'served_at'            => 'datetime',
            'cancelled_at'         => 'datetime',
            'waiter_confirmed'     => 'boolean',
            'waiter_confirmed_at'  => 'datetime',
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

    public function tableSession(): BelongsTo
    {
        return $this->belongsTo(TableSession::class);
    }
}
