<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    protected $fillable = [
        'vendor_id',
        'card_brand',
        'last4',
        'exp_month',
        'exp_year',
        'stripe_payment_method_id',
        'is_default',
        'billing_email',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }
}
