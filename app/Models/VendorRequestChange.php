<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorRequestChange extends Model
{
    protected $fillable = [
        'vendor_id',
        'restaurant_name',
        'legal_entity_name',
        'business_registration_number',
        'vat_number',
        'company_type',
        'country',
        'city',
        'postal_code',
        'address',
        'fon_participant_id',
        'fon_user_id',
        'fon_user_pin',
        'admin_notes',
        'vendor_notes',
        'status',
        'checked_by',
        'reviewed_at',
    ];

    protected $casts = [
        'status' => 'string',
        'reviewed_at' => 'datetime',
        // The vendor's FinanzOnline PIN. Encrypted here and cleared once the
        // approval has handed it to the fiscal device.
        'fon_user_pin' => 'encrypted',
    ];

    protected $hidden = [
        'fon_user_pin',
    ];

    /** Whether this request carries cash register details for an admin to approve. */
    public function hasFiscalDetails(): bool
    {
        return filled($this->fon_participant_id)
            || filled($this->fon_user_id)
            || filled($this->getAttributes()['fon_user_pin'] ?? null);
    }

    /** @return array<string, string> */
    public function fiscalCredentials(): array
    {
        return array_filter([
            'fon_participant_id' => $this->fon_participant_id,
            'fon_user_id' => $this->fon_user_id,
            'fon_user_pin' => $this->fon_user_pin,
        ], fn ($value) => filled($value));
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function checker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }
}
