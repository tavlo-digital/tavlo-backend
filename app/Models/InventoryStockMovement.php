<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryStockMovement extends Model
{
    protected $fillable = [
        'vendor_id',
        'inventory_item_id',
        'order_id',
        'cart_item_id',
        'type',
        'source',
        'quantity_change',
        'quantity_before',
        'quantity_after',
        'actor_name',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'quantity_change' => 'decimal:3',
            'quantity_before' => 'decimal:3',
            'quantity_after' => 'decimal:3',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function cartItem(): BelongsTo
    {
        return $this->belongsTo(CartItem::class);
    }
}
