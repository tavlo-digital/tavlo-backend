<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ModifierGroup extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'product_uid',
        'vendor_id',
        'name',
        'type',
        'min_selection',
        'max_selection',
        'is_required',
        'tax_category',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ModifierGroup $group) {
            if (empty($group->product_uid)) {
                $group->product_uid = (string) Str::uuid();
            }
        });
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(ModifierOption::class)->orderBy('sort_order');
    }

    public function menuItems(): BelongsToMany
    {
        return $this->belongsToMany(MenuItem::class, 'menu_item_modifier_groups')
            ->withPivot('sort_order')
            ->withTimestamps();
    }

    public function localizedTranslations(): HasMany
    {
        return $this->hasMany(ModifierGroupTranslation::class);
    }
}
