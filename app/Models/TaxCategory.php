<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaxCategory extends Model
{
    protected $fillable = [
        'country',
        'slug',
        'name',
        'vat_rate',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'vat_rate' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function menuCategories(): HasMany
    {
        return $this->hasMany(MenuCategory::class);
    }
}
