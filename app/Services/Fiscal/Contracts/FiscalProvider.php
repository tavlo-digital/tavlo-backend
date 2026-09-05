<?php

namespace App\Services\Fiscal\Contracts;

use App\Models\FiscalDevice;
use App\Models\FiscalReceipt;
use App\Models\Vendor;

interface FiscalProvider
{
    /** ISO alpha-2 country this provider fiscalizes for. */
    public function country(): string;

    /**
     * Register and initialize the vendor's till at the provider. Idempotent:
     * both APIs address resources by client-chosen id, so re-running picks up
     * where a failed attempt stopped.
     *
     * @param  array<string, mixed>  $credentials
     */
    public function provision(Vendor $vendor, array $credentials = []): FiscalDevice;

    /**
     * Sign one receipt and return the fields it has to display.
     *
     * @return array{
     *     qr_code_data: string|null,
     *     signature: string|null,
     *     signature_counter: string|null,
     *     receipt_number: string|null,
     *     register_serial_number: string|null,
     *     response: array<string, mixed>
     * }
     */
    public function sign(FiscalReceipt $receipt, FiscalDevice $device): array;
}
