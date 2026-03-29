<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SpecialTag extends Model
{
    protected $fillable = [
        'slug',
        'label',
        'icon',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
