<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reservation extends Model
{
    protected $fillable = [
        'reservation_public_id',
        'vendor_id',
        'customer_id',
        'guest_name',
        'guest_email',
        'guest_phone',
        'date',
        'time',
        'party_size',
        'status',
        'customer_note',
        'vendor_note',
        'table_number',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
