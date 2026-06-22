<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpecialTagTranslation extends Model
{
    protected $fillable = ['special_tag_id', 'language', 'label'];

    public function specialTag(): BelongsTo
    {
        return $this->belongsTo(SpecialTag::class);
    }
}
