<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Vendor extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected static function booted(): void
    {
        static::creating(function (Vendor $vendor) {
            if (empty($vendor->vendor_public_id)) {
                do {
                    $id = 'VID-'.str_pad(random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
                } while (static::where('vendor_public_id', $id)->exists());
                $vendor->vendor_public_id = $id;
            }
        });
    }

    protected $fillable = [
        'vendor_public_id',
        'slug',
        'name',
        'restaurant_name',
        'legal_entity_name',
        'business_registration_number',
        'vat_number',
        'website',
        'country',
        'city',
        'address',
        'latitude',
        'longitude',
        'phone',
        'email',
        'password',
        'status',
        'live_status',
        'risk_level',
        'orders_count',
        'revenue_total',
        'users_used',
        'payment_status',
        'payment_last_success',
        'payment_failures_24h',
        'last_activity_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'payment_last_success' => 'datetime',
            'password' => 'hashed',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    public function requestChanges(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(VendorRequestChange::class);
    }

    public function subscriptions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function activities(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(VendorActivity::class);
    }

    public function menuCategories(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(MenuCategory::class);
    }

    public function menuItems(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(MenuItem::class);
    }

    public function modifierGroups(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ModifierGroup::class);
    }

    public function inventoryItems(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(InventoryItem::class);
    }

    public function inventoryCategories(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(InventoryCategory::class);
    }

    public function inventorySettings(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(InventorySettings::class);
    }

    public function orders(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function reviews(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function reservations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function vendorSetting(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(VendorSetting::class);
    }

    public function restaurantTables(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(RestaurantTable::class);
    }

    public function teamMembers(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TeamMember::class);
    }

    public function takeawayQr(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(VendorTakeawayQr::class);
    }

    public function tableSessions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TableSession::class);
    }

    public function paymentMethods(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PaymentMethod::class);
    }

    public function orderPayments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(OrderPayment::class);
    }
}
