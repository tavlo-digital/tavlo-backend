<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DietaryPreference extends Model
{
    protected $fillable = ['slug', 'name', 'icon', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function localizedTranslations(): HasMany
    {
        return $this->hasMany(DietaryPreferenceTranslation::class);
    }
}
