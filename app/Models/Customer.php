<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Customer extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'customer_public_id',
        'first_name',
        'last_name',
        'phone',
        'email',
        'password',
        'gender',
        'date_of_birth',
        'address',
        'profile_picture',
        'social_provider',
        'social_provider_id',
        'phone_verified',
        'email_verified_at',
        'account_type',
        'risk_level',
        'risk_tooltip',
        'orders_count',
        'total_spend',
        'last_active_at',
        'loyalty_points',
        'registration_source',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'social_provider_id',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_active_at' => 'datetime',
            'date_of_birth' => 'date',
            'phone_verified' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(CustomerActivity::class);
    }

    public function gdprRequests(): HasMany
    {
        return $this->hasMany(GdprRequest::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function favorites(): BelongsToMany
    {
        return $this->belongsToMany(Vendor::class, 'customer_favorites')->withTimestamps();
    }

    public function loyaltyPoints(): HasMany
    {
        return $this->hasMany(CustomerLoyaltyPoint::class);
    }

    public function loyaltyTransactions(): HasMany
    {
        return $this->hasMany(LoyaltyTransaction::class);
    }
}
