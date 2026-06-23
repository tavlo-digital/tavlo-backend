<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TableScanSession extends Model
{
    protected $fillable = [
        'vendor_id',
        'restaurant_table_id',
        'customer_id',
        'pin',
        'status',
        'scanned_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'scanned_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function restaurantTable(): BelongsTo
    {
        return $this->belongsTo(RestaurantTable::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class, 'table_scan_session_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'table_scan_session_id');
    }

    public function review(): HasOne
    {
        return $this->hasOne(Review::class, 'table_scan_session_id');
    }

    public function sessionActivities(): HasMany
    {
        return $this->hasMany(CustomerSessionActivity::class);
    }

    /**
     * Generate a 4-digit PIN that is unique across all currently-active sessions.
     */
    public static function generateUniquePin(): string
    {
        do {
            $pin = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            $exists = self::where('status', 'active')->where('pin', $pin)->exists();
        } while ($exists);

        return $pin;
    }
}
