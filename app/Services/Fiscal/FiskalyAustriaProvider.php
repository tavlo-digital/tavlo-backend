<?php

namespace App\Services\Fiscal;

use App\Exceptions\FiscalizationException;
use App\Models\FiscalDevice;
use App\Models\FiscalReceipt;
use App\Models\Vendor;
use App\Services\Fiscal\Contracts\FiscalProvider;
use Illuminate\Support\Str;

/**
 * fiskaly SIGN AT — Austrian RKSV.
 *
 * Austria signs once, when the receipt is issued, so there is no transaction to
 * open beforehand. fiskaly handles the FinanzOnline registration itself once the
 * SCU is initialized with the vendor's web-service credentials.
 */
class FiskalyAustriaProvider implements FiscalProvider
{
    public function __construct(private readonly FiskalyClient $client) {}

    public function country(): string
    {
        return 'AT';
    }

    public function provision(Vendor $vendor, array $credentials = []): FiscalDevice
    {
        $device = FiscalDevice::firstOrNew(['vendor_id' => $vendor->id]);

        // Keep the Unit API credentials alongside the FinanzOnline values. The
        // whole column is encrypted at rest.
        $fonCredentials = array_intersect_key($credentials, array_flip([
            'fon_participant_id',
            'fon_user_id',
            'fon_user_pin',
        ]));

        $device->fill([
            'provider' => 'fiskaly',
            'country' => 'AT',
            'environment' => (string) config('services.fiskaly.environment', 'sandbox'),
            'signature_unit_id' => $device->signature_unit_id ?: (string) Str::uuid(),
            'register_id' => $device->register_id ?: (string) Str::uuid(),
            'credentials' => [...($device->credentials ?? []), ...$fonCredentials],
        ]);
        $device->serial_number ??= 'TAVLO-'.$vendor->vendor_public_id;
        $device->save();

        $fon = $device->credentials ?? [];

        foreach (['fon_participant_id', 'fon_user_id', 'fon_user_pin'] as $key) {
            if (blank($fon[$key] ?? null)) {
                throw new FiscalizationException(
                    'FinanzOnline web-service credentials are required to provision an Austrian vendor.',
                    ['missing' => $key, 'vendor_id' => $vendor->id],
                );
            }
        }

        // During activation the VAT number is still awaiting Tavlo approval and
        // is not yet on the vendor record, so the caller may supply it.
        $vatNumber = $credentials['vat_id'] ?? $vendor->vat_number;

        if (blank($vatNumber)) {
            throw new FiscalizationException('The vendor has no VAT number to register with.', [
                'vendor_id' => $vendor->id,
            ]);
        }

        // FinanzOnline credentials, then the SCU, then the register. Each step
        // is a PUT/PATCH on an id we chose, so a retry resumes rather than
        // duplicating.
        $this->client->put('/fon/auth', [
            'fon_participant_id' => $fon['fon_participant_id'],
            'fon_user_id' => $fon['fon_user_id'],
            'fon_user_pin' => $fon['fon_user_pin'],
        ]);

        $this->client->put("/signature-creation-unit/{$device->signature_unit_id}", [
            'legal_entity_id' => ['vat_id' => $vatNumber],
        ]);
        $this->client->patch("/signature-creation-unit/{$device->signature_unit_id}", [
            'state' => 'INITIALIZED',
        ]);

        $this->client->put("/cash-register/{$device->register_id}", [
            'description' => $vendor->restaurant_name ?: $vendor->name,
        ]);
        $this->client->patch("/cash-register/{$device->register_id}", ['state' => 'REGISTERED']);
        $device->forceFill([
            'state' => FiscalDevice::STATE_REGISTERED,
            'registered_at' => now(),
        ])->save();

        // Initializing the register is what produces the RKSV start receipt.
        $this->client->patch("/cash-register/{$device->register_id}", ['state' => 'INITIALIZED']);

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

        $response = $this->client->put(
            "/cash-register/{$device->register_id}/receipt/{$receipt->external_id}",
            [
                'receipt_type' => 'NORMAL',
                // fiskaly's standard_v1 schema takes these three keys directly
                // — not nested under a "receipt" key, and line_items is
                // required, not optional.
                'schema' => [
                    'standard_v1' => [
                        'amounts_per_vat_rate' => $payload['amounts_per_vat_rate'],
                        'amounts_per_payment_type' => $payload['amounts_per_payment_type'],
                        'line_items' => $payload['line_items'],
                    ],
                ],
            ],
        );

        return [
            'qr_code_data' => $response['qr_code_data'] ?? null,
            'signature' => $response['signature'] ?? null,
            'signature_counter' => isset($response['signature_counter'])
                ? (string) $response['signature_counter']
                : null,
            'receipt_number' => isset($response['receipt_number'])
                ? (string) $response['receipt_number']
                : null,
            'register_serial_number' => $response['cash_register_serial_number']
                ?? $device->serial_number,
            'response' => $response,
        ];
    }
}
