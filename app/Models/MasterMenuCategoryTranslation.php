<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MasterMenuCategoryTranslation extends Model
{
    protected $fillable = ['master_menu_category_id', 'language', 'name'];

    public function masterMenuCategory(): BelongsTo
    {
        return $this->belongsTo(MasterMenuCategory::class);
    }
}
