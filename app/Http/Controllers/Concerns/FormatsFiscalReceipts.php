<?php

namespace App\Http\Controllers\Concerns;

use App\Models\FiscalReceipt;
use App\Models\Order;

trait FormatsFiscalReceipts
{
    /**
     * The fiscal block for a receipt response.
     *
     * `null` for vendors in countries that are not fiscalized, so existing
     * clients see no change. Where fiscalization does apply the state is always
     * reported: a receipt awaiting or failing signature must not be presented as
     * though it were final.
     *
     * @return array<string, mixed>|null
     */
    private function fiscalBlock(Order $order): ?array
    {
        $receipt = FiscalReceipt::where('order_id', $order->id)->first();

        if (! $receipt) {
            return null;
        }

        return [
            'required' => true,
            'state' => $receipt->state,
            'country' => $receipt->country,
            'provider' => $receipt->provider,
            'signed_at' => $receipt->signed_at?->toISOString(),
            'qr_code_data' => $receipt->qr_code_data,
            'signature' => $receipt->signature,
            'signature_counter' => $receipt->signature_counter,
            'receipt_number' => $receipt->receipt_number,
            'register_serial_number' => $receipt->register_serial_number,
            'amounts_per_vat_rate' => $receipt->payload['amounts_per_vat_rate'] ?? [],
            'amounts_per_payment_type' => $receipt->payload['amounts_per_payment_type'] ?? [],
        ];
    }
}
