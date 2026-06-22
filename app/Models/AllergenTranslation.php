<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AllergenTranslation extends Model
{
    protected $fillable = ['allergen_id', 'language', 'name'];

    public function allergen(): BelongsTo
    {
        return $this->belongsTo(Allergen::class);
    }
}
