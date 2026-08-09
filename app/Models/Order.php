<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use HasFactory;

    public const STATUS_DRAFT              = 'draft';
    public const STATUS_CONFIRMED          = 'confirmed';
    public const STATUS_WAITER_CONFIRMED   = 'waiter_confirmed';
    public const STATUS_IN_PROGRESS        = 'in_progress';
    public const STATUS_SERVED             = 'served';
    public const STATUS_PICKED_UP          = 'picked_up';
    public const STATUS_CANCELLED          = 'cancelled';

    public const COMPLETED_STATUSES = [self::STATUS_SERVED, self::STATUS_PICKED_UP];
    public const TERMINAL_STATUSES  = [self::STATUS_SERVED, self::STATUS_PICKED_UP, self::STATUS_CANCELLED];
    public const ACTIVE_STATUSES    = [self::STATUS_CONFIRMED, self::STATUS_WAITER_CONFIRMED, self::STATUS_IN_PROGRESS];

    protected $fillable = [
        'order_public_id',
        'invoice_number',
        'customer_id',
        'paid_by',
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
        'draft_at',
        'confirmed_at',
        'kitchen_released_at',
        'in_progress_at',
        'served_at',
        'picked_up_at',
        'cancelled_at',
        'cancelled_reason',
        // session fields
        'table_scan_session_id',
        'waiter_confirmed',
        'waiter_confirmed_at',
        // attribution
        'placed_by',
        'placed_by_team_member_id',
        // side order holding a covered customer's personal opt-in shares
        'parent_order_id',
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
            'draft_at'             => 'datetime',
            'confirmed_at'         => 'datetime',
            'kitchen_released_at'  => 'datetime',
            'in_progress_at'       => 'datetime',
            'served_at'            => 'datetime',
            'picked_up_at'         => 'datetime',
            'cancelled_at'         => 'datetime',
            'waiter_confirmed'     => 'boolean',
            'waiter_confirmed_at'  => 'datetime',
        ];
    }

    public function status(): string
    {
        if ($this->cancelled_at) return self::STATUS_CANCELLED;
        if ($this->picked_up_at) return self::STATUS_PICKED_UP;
        if ($this->served_at) return self::STATUS_SERVED;
        if ($this->in_progress_at) return self::STATUS_IN_PROGRESS;
        if ($this->waiter_confirmed_at) return self::STATUS_WAITER_CONFIRMED;
        if ($this->confirmed_at) return self::STATUS_CONFIRMED;
        return self::STATUS_DRAFT;
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'paid_by');
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

    public function coveredPayments(): BelongsToMany
    {
        return $this->belongsToMany(OrderPayment::class, 'order_payment_orders')
            ->withPivot('amount')
            ->withTimestamps();
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }

    public function parentOrder(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_order_id');
    }

    public function shareOrder(): HasOne
    {
        return $this->hasOne(self::class, 'parent_order_id');
    }
}
