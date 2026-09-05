<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A vendor's registered till at the fiscalization provider: an SCU plus cash
 * register in Austria, a TSS plus client in Germany.
 */
class FiscalDevice extends Model
{
    public const STATE_PENDING = 'pending';

    /** Vendor supplied their details; waiting on a Tavlo admin to approve. */
    public const STATE_AWAITING_APPROVAL = 'awaiting_approval';

    public const STATE_REGISTERED = 'registered';

    public const STATE_INITIALIZED = 'initialized';

    public const STATE_FAILED = 'failed';

    public const STATE_DISABLED = 'disabled';

    protected $fillable = [
        'vendor_id',
        'provider',
        'country',
        'environment',
        'fiskaly_organization_id',
        'fiskaly_api_key_id',
        'signature_unit_id',
        'register_id',
        'serial_number',
        'state',
        'submitted_at',
        'last_attempted_at',
        'last_error',
        'credentials',
        'registered_at',
        'initialized_at',
    ];

    protected $hidden = [
        'credentials',
    ];

    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'submitted_at' => 'datetime',
            'last_attempted_at' => 'datetime',
            'registered_at' => 'datetime',
            'initialized_at' => 'datetime',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function isUsable(): bool
    {
        return $this->state === self::STATE_INITIALIZED
            && $this->signature_unit_id
            && $this->register_id;
    }

    /** The vendor has done their part and is waiting on us. */
    public function isAwaitingApproval(): bool
    {
        return $this->state === self::STATE_AWAITING_APPROVAL;
    }

    /** Registration has been submitted but has not succeeded yet. */
    public function needsRegistration(): bool
    {
        return in_array($this->state, [
            self::STATE_AWAITING_APPROVAL,
            self::STATE_PENDING,
            self::STATE_REGISTERED,
            self::STATE_FAILED,
        ], true);
    }
}
