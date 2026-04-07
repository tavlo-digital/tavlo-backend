<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $fillable = [
        'subscription_id',
        'invoice_number',
        'amount',
        'vat',
        'currency',
        'status',
        'billing_period_start',
        'billing_period_end',
        'due_date',
        'paid_at',
        'pdf_url',
        'stripe_invoice_id',
        'stripe_hosted_url',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'vat' => 'decimal:2',
            'billing_period_start' => 'date',
            'billing_period_end' => 'date',
            'due_date' => 'date',
            'paid_at' => 'datetime',
        ];
    }

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
}
