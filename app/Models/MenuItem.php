<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MenuItem extends Model
{
    protected $fillable = [
        'vendor_id',
        'menu_category_id',
        'name',
        'description',
        'price',
        'image_url',
        'available',
        'is_active',
        'calories',
        'fat',
        'carbs',
        'protein',
        'vat_rate',
        'tax_category',
        'dietary_preference',
        'allergies',
        'special_tags',
        'has_discount',
        'discount_percent',
        'discounted_price',
        'paid_addons',
        'free_addons',
        'removable_items',
        'translations',
        'ingredients',
        'rating',
        'review_count',
        'ordered_count',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'fat' => 'decimal:2',
            'carbs' => 'decimal:2',
            'protein' => 'decimal:2',
            'vat_rate' => 'decimal:2',
            'discount_percent' => 'decimal:2',
            'discounted_price' => 'decimal:2',
            'rating' => 'decimal:2',
            'available' => 'boolean',
            'has_discount' => 'boolean',
            'allergies' => 'array',
            'special_tags' => 'array',
            'paid_addons' => 'array',
            'free_addons' => 'array',
            'removable_items' => 'array',
            'translations' => 'array',
            'ingredients' => 'array',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(MenuCategory::class, 'menu_category_id');
    }

    public function itemTranslations(): HasMany
    {
        return $this->hasMany(MenuItemTranslation::class);
    }

    public function allergens(): BelongsToMany
    {
        return $this->belongsToMany(Allergen::class, 'menu_item_allergens')->withTimestamps();
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(SpecialTag::class, 'menu_item_tags')->withTimestamps();
    }

    public function modifierGroups(): BelongsToMany
    {
        return $this->belongsToMany(ModifierGroup::class, 'menu_item_modifier_groups')
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    public function recipeIngredients(): HasMany
    {
        return $this->hasMany(MenuItemIngredient::class);
    }
}
