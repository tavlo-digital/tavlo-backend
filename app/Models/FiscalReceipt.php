<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The immutable snapshot of a receipt and the signature returned for it.
 *
 * The payload is stored as sent rather than recomputed on read: a signature is
 * over a fixed document, and menu prices and VAT rates move.
 */
class FiscalReceipt extends Model
{
    public const STATE_PENDING = 'pending';

    public const STATE_SIGNED = 'signed';

    public const STATE_FAILED = 'failed';

    protected $fillable = [
        'order_id',
        'vendor_id',
        'provider',
        'country',
        'invoice_number',
        'external_id',
        'state',
        'payload',
        'response',
        'qr_code_data',
        'signature',
        'signature_counter',
        'receipt_number',
        'register_serial_number',
        'signed_at',
        'total_gross',
        'currency',
        'attempts',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'response' => 'array',
            'signed_at' => 'datetime',
            'total_gross' => 'decimal:2',
            'attempts' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function isSigned(): bool
    {
        return $this->state === self::STATE_SIGNED;
    }
}
