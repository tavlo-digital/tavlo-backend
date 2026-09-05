<?php

namespace App\Console\Commands;

use App\Models\Vendor;
use App\Services\Fiscal\FiscalizationService;
use Illuminate\Console\Command;
use Throwable;

class FiskalyProvisionVendor extends Command
{
    protected $signature = 'fiskaly:provision
        {vendor : Vendor id, public id or slug}
        {--fon-participant-id= : FinanzOnline participant id (Austria)}
        {--fon-user-id= : FinanzOnline web-service user id (Austria)}
        {--fon-user-pin= : FinanzOnline web-service user PIN (Austria)}';

    protected $description = 'Register a vendor as a cash register at fiskaly (SIGN AT / SIGN DE).';

    public function handle(FiscalizationService $fiscalization): int
    {
        $vendor = Vendor::query()
            ->where('vendor_public_id', $this->argument('vendor'))
            ->orWhere('slug', $this->argument('vendor'))
            ->when(ctype_digit((string) $this->argument('vendor')), fn ($query) => $query
                ->orWhere('id', (int) $this->argument('vendor')))
            ->first();

        if (! $vendor) {
            $this->error('No vendor matched "'.$this->argument('vendor').'".');

            return self::FAILURE;
        }

        if (! $fiscalization->enabled()) {
            $this->error('FISKALY_ENABLED is off. Turn it on before provisioning.');

            return self::FAILURE;
        }

        $country = $fiscalization->countryCode($vendor);

        if (! $fiscalization->required($vendor)) {
            $this->error("Vendor country {$country} is not in FISKALY_COUNTRIES.");

            return self::FAILURE;
        }

        $credentials = array_filter([
            'fon_participant_id' => $this->option('fon-participant-id'),
            'fon_user_id' => $this->option('fon-user-id'),
            'fon_user_pin' => $this->option('fon-user-pin'),
        ]);

        $this->info("Provisioning {$vendor->restaurant_name} ({$country}) in "
            .config('services.fiskaly.environment').'…');

        try {
            $device = $fiscalization->provision($vendor, $credentials);
        } catch (Throwable $exception) {
            $this->error('Provisioning failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->table(['Field', 'Value'], [
            ['State', $device->state],
            [$country === 'AT' ? 'SCU' : 'TSS', $device->signature_unit_id],
            [$country === 'AT' ? 'Cash register' : 'Client', $device->register_id],
            ['Serial number', $device->serial_number],
        ]);

        return self::SUCCESS;
    }
}
