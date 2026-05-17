<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    protected $fillable = [
        'table_scan_session_id',
        'menu_item_id',
        'order_id',
        'quantity',
        'notes',
        'paid_addons',
        'free_addons',
        'removed_items',
        'selected_modifiers',
        'shared_order_ids',
        'preparing_start_at',
        'ready_at',
        'served_at',
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
            'preparing_start_at' => 'datetime',
            'ready_at'           => 'datetime',
            'served_at'          => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(TableScanSession::class, 'table_scan_session_id');
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
