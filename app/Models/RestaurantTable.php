<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class RestaurantTable extends Model
{
    protected $table = 'restaurant_tables';

    protected $fillable = [
        'vendor_id',
        'number',
        'name',
        'qr_token',
        'is_active',
        'qr_created_at',
        'last_scanned_at',
        'call_waiter_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active'      => 'boolean',
            'qr_created_at'  => 'datetime',
            'last_scanned_at' => 'datetime',
            'call_waiter_at' => 'datetime',
        ];
    }

    public function vendor(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /**
     * Generate a fresh unique QR token.
     */
    public static function generateQrToken(): string
    {
        do {
            $token = Str::uuid()->toString();
        } while (self::where('qr_token', $token)->exists());

        return $token;
    }

    /**
     * Refresh the QR token for this table.
     */
    public function refreshQr(): void
    {
        $this->update([
            'qr_token'    => self::generateQrToken(),
            'qr_created_at' => now(),
        ]);
    }
}
