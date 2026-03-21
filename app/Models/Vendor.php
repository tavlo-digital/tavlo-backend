<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Vendor extends Authenticatable
{
    use HasApiTokens, Notifiable;

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
}
