<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryCategory extends Model
{
    protected $fillable = ['vendor_id', 'name', 'sort_order'];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryItem::class);
    }

    public function localizedTranslations(): HasMany
    {
        return $this->hasMany(InventoryCategoryTranslation::class);
    }
}
