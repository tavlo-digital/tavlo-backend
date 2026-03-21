<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorRequestChange extends Model
{
    protected $fillable = [
        'vendor_id',
        'restaurant_name',
        'legal_entity_name',
        'business_registration_number',
        'vat_number',
        'country',
        'city',
        'address',
        'admin_notes',
        'vendor_notes',
        'status',
        'checked_by',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    public function vendor(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function checker(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }
}
