<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorTakeawayQr extends Model
{
    protected $table = 'vendor_takeaway_qrs';

    protected $fillable = [
        'vendor_id',
        'qr_token',
        'last_regenerated_at',
        'last_scanned_at',
    ];

    protected function casts(): array
    {
        return [
            'last_regenerated_at' => 'datetime',
            'last_scanned_at'     => 'datetime',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }
}
