<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ModifierOption extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'modifier_group_id',
        'name',
        'price_adjustment',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price_adjustment' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(ModifierGroup::class, 'modifier_group_id');
    }

    public function localizedTranslations(): HasMany
    {
        return $this->hasMany(ModifierOptionTranslation::class);
    }
}
