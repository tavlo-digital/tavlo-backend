<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModifierOptionTranslation extends Model
{
    protected $fillable = ['modifier_option_id', 'language', 'name'];

    public function option(): BelongsTo
    {
        return $this->belongsTo(ModifierOption::class, 'modifier_option_id');
    }
}
