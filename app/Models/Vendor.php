<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class Vendor extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected static function booted(): void
    {
        static::saving(function (Vendor $vendor) {
            if (empty($vendor->vendor_public_id)) {
                do {
                    $id = 'VID-'.str_pad(random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
                } while (static::where('vendor_public_id', $id)->exists());
                $vendor->vendor_public_id = $id;
            }

            if (trim((string) $vendor->slug) === '') {
                $vendor->slug = static::generateUniqueSlug(
                    $vendor->restaurant_name
                        ?: $vendor->name
                        ?: $vendor->vendor_public_id,
                    $vendor->exists ? $vendor->getKey() : null,
                );
            }
        });
    }

    public static function generateUniqueSlug(string $value, int|string|null $ignoreId = null): string
    {
        $base = Str::limit(Str::slug($value) ?: 'vendor', 240, '');
        $slug = $base;
        $suffix = 2;

        while (static::query()
            ->where('slug', $slug)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists()) {
            $ending = '-'.$suffix++;
            $slug = Str::limit($base, 255 - strlen($ending), '').$ending;
        }

        return $slug;
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

    public function requestChanges(): HasMany
    {
        return $this->hasMany(VendorRequestChange::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(VendorActivity::class);
    }

    public function menuCategories(): HasMany
    {
        return $this->hasMany(MenuCategory::class);
    }

    public function menuItems(): HasMany
    {
        return $this->hasMany(MenuItem::class);
    }

    public function modifierGroups(): HasMany
    {
        return $this->hasMany(ModifierGroup::class);
    }

    public function inventoryItems(): HasMany
    {
        return $this->hasMany(InventoryItem::class);
    }

    public function inventoryCategories(): HasMany
    {
        return $this->hasMany(InventoryCategory::class);
    }

    public function inventorySettings(): HasOne
    {
        return $this->hasOne(InventorySettings::class);
    }

    public function inventoryPurchaseOrders(): HasMany
    {
        return $this->hasMany(InventoryPurchaseOrder::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function vendorSetting(): HasOne
    {
        return $this->hasOne(VendorSetting::class);
    }

    public function countryRecord(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country', 'code');
    }

    public function resolveCurrency(): string
    {
        $country = trim((string) $this->country);

        if ($country === '') {
            return 'EUR';
        }

        $countryRecord = $this->relationLoaded('countryRecord')
            ? $this->countryRecord
            : null;

        if (! $countryRecord) {
            $countryRecord = Country::query()
                ->where(function ($query) use ($country) {
                    $query->whereRaw('LOWER(code) = ?', [strtolower($country)])
                        ->orWhereRaw('LOWER(name) = ?', [strtolower($country)]);
                })
                ->first(['currency']);
        }

        return strtoupper($countryRecord?->currency ?: 'EUR');
    }

    public function getCurrencyAttribute(): string
    {
        return $this->resolveCurrency();
    }

    public function resolveTimezone(): string
    {
        $country = trim((string) $this->country);

        if ($country === '') {
            return 'UTC';
        }

        $countryRecord = $this->relationLoaded('countryRecord')
            ? $this->countryRecord
            : null;

        if (! $countryRecord) {
            $countryRecord = Country::query()
                ->where(function ($query) use ($country) {
                    $query->whereRaw('LOWER(code) = ?', [strtolower($country)])
                        ->orWhereRaw('LOWER(name) = ?', [strtolower($country)]);
                })
                ->first(['timezone']);
        }

        return $countryRecord?->timezone ?: 'UTC';
    }

    public function restaurantTables(): HasMany
    {
        return $this->hasMany(RestaurantTable::class);
    }

    public function teamMembers(): HasMany
    {
        return $this->hasMany(TeamMember::class);
    }

    public function takeawayQr(): HasOne
    {
        return $this->hasOne(VendorTakeawayQr::class);
    }

    public function tableSessions(): HasMany
    {
        return $this->hasMany(TableSession::class);
    }

    public function paymentMethods(): HasMany
    {
        return $this->hasMany(PaymentMethod::class);
    }

    public function orderPayments(): HasMany
    {
        return $this->hasMany(OrderPayment::class);
    }
}
