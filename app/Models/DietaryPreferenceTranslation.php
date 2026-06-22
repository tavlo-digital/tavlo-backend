<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DietaryPreferenceTranslation extends Model
{
    protected $fillable = ['dietary_preference_id', 'language', 'name'];

    public function dietaryPreference(): BelongsTo
    {
        return $this->belongsTo(DietaryPreference::class);
    }
}
