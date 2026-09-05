<?php

namespace Tests\Support;

use App\Models\FiscalDevice;
use App\Models\FiscalReceipt;
use App\Models\Vendor;
use App\Services\Fiscal\Contracts\FiscalProvider;
use App\Services\Fiscal\FiskalyClient;
use Illuminate\Support\Str;

/**
 * Stands in for a country fiskaly supports that Tavlo has not written a real
 * provider for yet. Exists to prove that adding one is a config entry plus a
 * class, with no branching elsewhere in the codebase.
 */
class StubFiscalProvider implements FiscalProvider
{
    public function __construct(private readonly FiskalyClient $client) {}

    public function country(): string
    {
        return 'FR';
    }

    public function provision(Vendor $vendor, array $credentials = []): FiscalDevice
    {
        $device = FiscalDevice::firstOrNew(['vendor_id' => $vendor->id]);

        $device->fill([
            'provider' => 'fiskaly',
            'country' => 'FR',
            'environment' => (string) config('services.fiskaly.environment', 'sandbox'),
            'signature_unit_id' => $device->signature_unit_id ?: (string) Str::uuid(),
            'register_id' => $device->register_id ?: (string) Str::uuid(),
            'state' => FiscalDevice::STATE_INITIALIZED,
            'initialized_at' => now(),
            'last_error' => null,
        ]);
        $device->serial_number ??= 'TAVLO-'.$vendor->vendor_public_id;
        $device->save();

        return $device;
    }

    public function sign(FiscalReceipt $receipt, FiscalDevice $device): array
    {
        return [
            'qr_code_data' => 'stub-qr',
            'signature' => 'stub-signature',
            'signature_counter' => '1',
            'receipt_number' => '1',
            'register_serial_number' => $device->serial_number,
            'response' => [],
        ];
    }
}
