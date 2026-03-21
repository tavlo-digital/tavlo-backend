<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    protected $fillable = [
        'vendor_id',
        'plan_id',
        'status',
        'billing_cycle',
        'start_date',
        'next_billing_date',
        'auto_renew',
        'stripe_subscription_id',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'next_billing_date' => 'date',
            'auto_renew' => 'boolean',
        ];
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function plan()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    public function events()
    {
        return $this->hasMany(SubscriptionEvent::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }
}
