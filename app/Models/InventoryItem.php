<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryItem extends Model
{
    protected $fillable = [
        'vendor_id',
        'inventory_category_id',
        'name',
        'category',
        'quantity',
        'unit',
        'min_stock',
        'reorder_quantity',
        'cost_per_unit',
        'supplier',
        'is_critical',
        'auto_reorder',
        'track_stock',
        'nutrition',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'min_stock' => 'decimal:2',
            'reorder_quantity' => 'decimal:2',
            'cost_per_unit' => 'decimal:4',
            'is_critical' => 'boolean',
            'auto_reorder' => 'boolean',
            'track_stock' => 'boolean',
            'nutrition' => 'array',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function inventoryCategory(): BelongsTo
    {
        return $this->belongsTo(InventoryCategory::class);
    }

    public function localizedTranslations(): HasMany
    {
        return $this->hasMany(InventoryItemTranslation::class);
    }
}
