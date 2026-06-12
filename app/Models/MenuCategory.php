<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class MenuCategory extends Model
{
    private ?string $pendingMasterCategoryName = null;

    private ?string $pendingMasterCategorySlug = null;

    protected $fillable = [
        'vendor_id',
        'master_menu_category_id',
        'name',
        'slug',
        'default_tax_category',
        'tax_category_id',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (MenuCategory $category) {
            if ($category->master_menu_category_id || ! $category->pendingMasterCategoryName) {
                return;
            }

            $slug = $category->pendingMasterCategorySlug ?: Str::slug($category->pendingMasterCategoryName);
            $masterCategory = MasterMenuCategory::firstOrCreate(
                ['slug' => $slug],
                [
                    'name' => $category->pendingMasterCategoryName,
                    'is_active' => true,
                ]
            );

            $category->master_menu_category_id = $masterCategory->id;
        });
    }

    public function setNameAttribute(?string $value): void
    {
        $value = trim((string) $value);
        $this->pendingMasterCategoryName = $value !== '' ? $value : null;
    }

    public function setSlugAttribute(?string $value): void
    {
        $value = trim((string) $value);
        $this->pendingMasterCategorySlug = $value !== '' ? $value : null;
    }

    public function getNameAttribute(): string
    {
        return $this->display_name;
    }

    public function getSlugAttribute(): string
    {
        return $this->display_slug;
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function masterCategory(): BelongsTo
    {
        return $this->belongsTo(MasterMenuCategory::class, 'master_menu_category_id');
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->masterCategory?->name ?? 'Unknown';
    }

    public function getDisplaySlugAttribute(): string
    {
        return $this->masterCategory?->slug ?? 'unknown';
    }

    public function getDisplayIconAttribute(): ?string
    {
        return $this->masterCategory?->icon_url;
    }

    public function taxCategory(): BelongsTo
    {
        return $this->belongsTo(TaxCategory::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(MenuItem::class);
    }

    public function localizedTranslations(): HasMany
    {
        return $this->hasMany(MenuCategoryTranslation::class);
    }
}
