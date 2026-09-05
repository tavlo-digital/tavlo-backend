<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * Allocates vendor-scoped invoice numbers.
 *
 * Numbers used to be minted lazily by the receipt endpoints, so an order that
 * nobody opened never got one and the sequence followed viewing order rather
 * than payment order. Allocation now happens on the Order model the moment
 * payment_confirmed_at is set; this service is the single implementation both
 * that hook and the receipt fallback share.
 */
class InvoiceNumberService
{
    private const DEFAULT_PREFIX = 'INV';

    private const DEFAULT_NEXT_NUMBER = 1001;

    private const PAD_LENGTH = 7;

    /**
     * Reserve the vendor's next invoice number and advance the counter.
     *
     * The read and the increment are wrapped in a transaction because
     * lockForUpdate() only holds for the life of one — without it the lock was
     * released the moment the SELECT returned and two concurrent callers read
     * the same value, which then collided on the unique orders.invoice_number.
     *
     * Callers that already have a transaction open (the cash confirmation path,
     * for instance) get a savepoint here and hold the counter lock until their
     * own commit, so the number cannot be stranded if their write later fails.
     */
    public function allocate(int $vendorId): string
    {
        return DB::transaction(function () use ($vendorId) {
            // A vendor who has never saved Settings has no vendor_settings row,
            // so there was nothing to lock and nothing to increment: every one
            // of their orders fell back to the same default number.
            DB::table('vendor_settings')->insertOrIgnore([
                'vendor_id' => $vendorId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $settings = DB::table('vendor_settings')
                ->where('vendor_id', $vendorId)
                ->lockForUpdate()
                ->first(['invoice_prefix', 'next_invoice_number']);

            $prefix = trim((string) ($settings->invoice_prefix ?? '')) ?: self::DEFAULT_PREFIX;
            $number = (int) ($settings->next_invoice_number ?? self::DEFAULT_NEXT_NUMBER);

            DB::table('vendor_settings')
                ->where('vendor_id', $vendorId)
                ->update([
                    'next_invoice_number' => $number + 1,
                    'updated_at' => now(),
                ]);

            return $prefix.'-'.str_pad((string) $number, self::PAD_LENGTH, '0', STR_PAD_LEFT);
        });
    }

    /**
     * Return an order's invoice number, minting one if it predates assignment
     * at payment time. Only orders paid before that change reach the allocation
     * branch; everything settled since already carries a number.
     */
    public function forOrder(Order $order): string
    {
        if ($order->invoice_number) {
            return $order->invoice_number;
        }

        $invoiceNumber = $this->allocate((int) $order->vendor_id);

        $order->update(['invoice_number' => $invoiceNumber]);

        return $invoiceNumber;
    }
}
