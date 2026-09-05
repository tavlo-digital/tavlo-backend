<?php

namespace App\Services\Fiscal;

use App\Exceptions\FiscalizationException;
use App\Models\FiscalDevice;
use App\Models\FiscalReceipt;
use App\Models\Vendor;
use App\Services\Fiscal\Contracts\FiscalProvider;
use Illuminate\Support\Str;

/**
 * fiskaly SIGN DE — KassenSichV, via the cloud TSS.
 *
 * A German transaction has two states: ACTIVE when the recording process starts
 * and FINISHED when it is paid. Tavlo has no order-open hook yet, so both
 * revisions are sent back to back at payment. That satisfies the signature
 * requirement but not the "record from the start of the process" reading of the
 * ordinance — opening the transaction at Order::confirmed_at is the follow-up.
 */
class FiskalyGermanyProvider implements FiscalProvider
{
    public function __construct(private readonly FiskalyClient $client) {}

    public function country(): string
    {
        return 'DE';
    }

    public function provision(Vendor $vendor, array $credentials = []): FiscalDevice
    {
        $device = FiscalDevice::firstOrNew(['vendor_id' => $vendor->id]);

        $device->fill([
            'provider' => 'fiskaly',
            'country' => 'DE',
            'environment' => (string) config('services.fiskaly.environment', 'sandbox'),
            'signature_unit_id' => $device->signature_unit_id ?: (string) Str::uuid(),
            'register_id' => $device->register_id ?: (string) Str::uuid(),
        ]);
        $device->serial_number ??= 'TAVLO-'.$vendor->vendor_public_id;

        // The admin PIN is generated once and kept, because every later admin
        // action on this TSS needs it and fiskaly will not hand it back.
        $stored = $device->credentials ?? [];
        $adminPin = $credentials['admin_pin'] ?? $stored['admin_pin'] ?? Str::password(12, true, true, false);
        $device->credentials = [...$stored, 'admin_pin' => $adminPin];
        $device->save();

        $tss = $this->client->put("/tss/{$device->signature_unit_id}", [
            'description' => $vendor->restaurant_name ?: $vendor->name,
        ]);

        // The PUK only comes back on creation. On a re-run the TSS already has
        // its PIN set, so skip straight to authenticating with what we stored.
        if (! empty($tss['admin_puk'])) {
            $this->client->patch("/tss/{$device->signature_unit_id}/admin", [
                'admin_puk' => $tss['admin_puk'],
                'new_admin_pin' => $adminPin,
            ]);
        }

        $this->client->post("/tss/{$device->signature_unit_id}/admin/auth", [
            'admin_pin' => $adminPin,
        ]);

        try {
            $this->client->patch("/tss/{$device->signature_unit_id}", ['state' => 'INITIALIZED']);

            $device->forceFill([
                'state' => FiscalDevice::STATE_REGISTERED,
                'registered_at' => $device->registered_at ?? now(),
            ])->save();

            $this->client->put(
                "/tss/{$device->signature_unit_id}/client/{$device->register_id}",
                ['serial_number' => $device->serial_number],
            );
        } finally {
            // Never leave an admin session open on the vendor's TSS.
            $this->client->post("/tss/{$device->signature_unit_id}/admin/logout");
        }

        $device->forceFill([
            'state' => FiscalDevice::STATE_INITIALIZED,
            'initialized_at' => now(),
            'last_error' => null,
        ])->save();

        return $device;
    }

    public function sign(FiscalReceipt $receipt, FiscalDevice $device): array
    {
        $payload = $receipt->payload;
        $path = "/tss/{$device->signature_unit_id}/tx/{$receipt->external_id}";

        $this->client->put($path.'?tx_revision=1', [
            'state' => 'ACTIVE',
            'client_id' => $device->register_id,
        ]);

        $response = $this->client->put($path.'?tx_revision=2', [
            'state' => 'FINISHED',
            'client_id' => $device->register_id,
            'schema' => [
                'standard_v1' => [
                    'receipt' => [
                        'receipt_type' => 'RECEIPT',
                        'amounts_per_vat_rate' => $payload['amounts_per_vat_rate'],
                        'amounts_per_payment_type' => $payload['amounts_per_payment_type'],
                    ],
                ],
            ],
        ]);

        $signature = $response['signature'] ?? [];

        if (! is_array($signature)) {
            throw new FiscalizationException('fiskaly returned an unexpected signature shape.', [
                'receipt_id' => $receipt->id,
            ]);
        }

        return [
            'qr_code_data' => $response['qr_code_data'] ?? null,
            'signature' => $signature['value'] ?? null,
            'signature_counter' => isset($signature['counter'])
                ? (string) $signature['counter']
                : null,
            'receipt_number' => isset($response['number']) ? (string) $response['number'] : null,
            'register_serial_number' => $response['tss_serial_number'] ?? $device->serial_number,
            'response' => $response,
        ];
    }
}
