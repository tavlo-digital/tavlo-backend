<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    public const STATUS_NEW       = 'new';
    public const STATUS_RECEIVED  = 'received';
    public const STATUS_PREPARING = 'preparing';
    public const STATUS_READY     = 'ready';
    public const STATUS_SERVED    = 'served';
    public const STATUS_PICKED_UP = 'picked_up';

    public function status(): string
    {
        if ($this->picked_up_at) return self::STATUS_PICKED_UP;
        if ($this->served_at) return self::STATUS_SERVED;
        if ($this->ready_at) return self::STATUS_READY;
        if ($this->preparing_start_at) return self::STATUS_PREPARING;
        if ($this->received_at) return self::STATUS_RECEIVED;
        return self::STATUS_NEW;
    }

    protected $fillable = [
        'table_scan_session_id',
        'client_item_id',
        'menu_item_id',
        'order_id',
        'quantity',
        'notes',
        'paid_addons',
        'free_addons',
        'removed_items',
        'selected_modifiers',
        'shared_order_ids',
        'received_at',
        'preparing_start_at',
        'ready_at',
        'served_at',
        'picked_up_at',
        'inventory_deducted_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity'           => 'integer',
            'paid_addons'        => 'array',
            'free_addons'        => 'array',
            'removed_items'      => 'array',
            'selected_modifiers' => 'array',
            'shared_order_ids'          => 'array',
            'received_at'        => 'datetime',
            'preparing_start_at' => 'datetime',
            'ready_at'           => 'datetime',
            'served_at'          => 'datetime',
            'picked_up_at'       => 'datetime',
            'inventory_deducted_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(TableScanSession::class, 'table_scan_session_id');
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class)->withTrashed();
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
