<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A vendor-entered cost with no other real data source in Tavlo — rent,
 * repairs, new equipment, insurance, licenses, etc. See the migration doc
 * comment for why `is_recurring`/`recurrence_frequency` are metadata only,
 * not an auto-generating schedule.
 */
class FinancialExpense extends Model
{
    protected $fillable = [
        'vendor_id',
        'name',
        'category',
        'payee',
        'amount',
        'payment_method',
        'occurred_at',
        'description',
        'is_recurring',
        'recurrence_frequency',
        'attachment_path',
        'attachment_original_name',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'amount' => 'decimal:2',
            'is_recurring' => 'boolean',
        ];
    }

    public const CATEGORIES = [
        'rent',
        'utilities',
        'maintenance_repairs',
        'equipment',
        'supplies',
        'insurance',
        'marketing',
        'professional_services',
        'licenses_permits',
        'vat_payment',
        'other',
    ];

    public const PAYMENT_METHODS = ['cash', 'card', 'bank_transfer', 'other'];

    public const RECURRENCE_FREQUENCIES = ['daily', 'weekly', 'monthly', 'yearly'];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }
}
