<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    protected $fillable = [
        'delivery_key',
        'customer_id',
        'vendor_id',
        'waiter_id',
        'kitchen_id',
        'admin_id',
        'event',
        'message',
        'metadata',
        'read',
        'is_silent',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'read' => 'boolean',
            'is_silent' => 'boolean',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }
}
