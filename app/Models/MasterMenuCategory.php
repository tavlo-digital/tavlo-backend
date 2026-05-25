<?php

namespace App\Models;

use App\Services\MediaService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MasterMenuCategory extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'icon',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function vendorCategories(): HasMany
    {
        return $this->hasMany(MenuCategory::class);
    }

    public function getIconUrlAttribute(): ?string
    {
        $icon = $this->attributes['icon'] ?? null;

        if ($icon === null || $icon === '') {
            return null;
        }

        if (! preg_match('#^https?://#i', $icon) && ! str_contains($icon, '/')) {
            return null;
        }

        return app(MediaService::class)->url($icon);
    }
}
