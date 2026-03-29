<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryItem extends Model
{
    protected $fillable = [
        'vendor_id',
        'name',
        'category',
        'quantity',
        'unit',
        'min_stock',
        'cost_per_unit',
        'supplier',
        'is_critical',
        'auto_reorder',
        'nutrition',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'min_stock' => 'decimal:2',
            'cost_per_unit' => 'decimal:4',
            'is_critical' => 'boolean',
            'auto_reorder' => 'boolean',
            'nutrition' => 'array',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }
}
