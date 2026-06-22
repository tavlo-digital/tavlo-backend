<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class MenuItem extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'product_uid',
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

    protected static function booted(): void
    {
        static::creating(function (MenuItem $item) {
            if (empty($item->product_uid)) {
                $item->product_uid = (string) Str::uuid();
            }
        });
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

    /**
     * Resolve the live VAT rate from tax_categories table
     * based on the vendor's country and item's tax_category slug.
     * Falls back to the stored vat_rate if no match is found.
     *
     * Pass $vendorCountry to avoid an extra query when the vendor is already loaded.
     */
    public function liveVatRate(?string $vendorCountry = null): float
    {
        if ($this->tax_category) {
            if (! $vendorCountry) {
                $vendor = $this->relationLoaded('vendor')
                    ? $this->vendor
                    : Vendor::find($this->vendor_id);
                $vendorCountry = $vendor?->country ?? 'AT';
            }

            $country = $this->resolveCountryCode($vendorCountry);
            $tc = TaxCategory::where('country', $country)
                ->where('slug', $this->tax_category)
                ->where('is_active', true)
                ->first();

            if ($tc) {
                return (float) $tc->vat_rate;
            }
        }

        return (float) ($this->vat_rate ?? 0);
    }

    private function resolveCountryCode(string $country): string
    {
        $map = [
            'austria' => 'AT',
            'germany' => 'DE',
            'united kingdom' => 'GB',
            'uk' => 'GB',
            'great britain' => 'GB',
        ];

        return $map[strtolower(trim($country))] ?? strtoupper(substr($country, 0, 2));
    }
}
