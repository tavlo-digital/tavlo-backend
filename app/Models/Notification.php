<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $fillable = [
        'user_id',
        'event',
        'message',
        'read',
        'user_role',
    ];

    protected function casts(): array
    {
        return [
            'read' => 'boolean',
        ];
    }
}
