<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventorySettings extends Model
{
    protected $fillable = [
        'vendor_id',
        'low_stock_alerts',
        'auto_reorder_enabled',
        'low_stock_threshold',
        'track_nutrition',
        'link_menu_items',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'low_stock_alerts' => 'boolean',
            'auto_reorder_enabled' => 'boolean',
            'track_nutrition' => 'boolean',
            'link_menu_items' => 'boolean',
            'settings' => 'array',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }
}
