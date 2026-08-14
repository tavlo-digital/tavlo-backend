<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class InventoryPurchaseOrder extends Model
{
    protected $fillable = [
        'purchase_order_public_id',
        'vendor_id',
        'inventory_item_id',
        'supplier_id',
        'supplier_name',
        'supplier_email',
        'supplier_phone',
        'ordering_method',
        'ordering_url',
        'quantity',
        'unit',
        'unit_cost',
        'currency',
        'estimated_delivery_date',
        'notes',
        'status',
        'dispatched_at',
        'dispatch_error',
        'created_by_type',
        'created_by_id',
        'created_by_name',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_cost' => 'decimal:4',
            'estimated_delivery_date' => 'date',
            'dispatched_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (InventoryPurchaseOrder $purchaseOrder) {
            if ($purchaseOrder->purchase_order_public_id) {
                return;
            }

            do {
                $publicId = 'PO-'.Str::upper(Str::random(10));
            } while (static::where('purchase_order_public_id', $publicId)->exists());

            $purchaseOrder->purchase_order_public_id = $publicId;
        });
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }
}
