<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorActivity extends Model
{
    protected $fillable = [
        'vendor_id',
        'event_type',
        'title',
        'description',
        'color',
        'actor',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }
}
