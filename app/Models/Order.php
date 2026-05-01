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
        'shared_items',
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
        'table_scan_session_id',
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
            'shared_items'         => 'array',
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

    public function customer(): \Illuminate\Database\Eloquent\Relations\HasOneThrough
    {
        return $this->hasOneThrough(
            Customer::class,
            TableScanSession::class,
            'id',                    // FK on table_scan_sessions referenced by orders.table_scan_session_id
            'id',                    // FK on customers referenced by table_scan_sessions.customer_id
            'table_scan_session_id', // local key on orders
            'customer_id'            // local key on table_scan_sessions
        );
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function tableScanSession(): BelongsTo
    {
        return $this->belongsTo(TableScanSession::class);
    }
}
