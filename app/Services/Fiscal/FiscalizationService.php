<?php

namespace App\Services\Fiscal;

use App\Exceptions\FiscalizationException;
use App\Jobs\FiscalizeOrder;
use App\Models\FiscalDevice;
use App\Models\FiscalReceipt;
use App\Models\Order;
use App\Models\Vendor;
use App\Models\VendorRequestChange;
use App\Services\DeferredQueueDispatcher;
use App\Services\Fiscal\Contracts\FiscalProvider;
use App\Services\TaxCalculationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * The single entry point for fiscalizing an order.
 *
 * Signing happens on a queue: a diner must never wait on fiskaly, and a fiskaly
 * outage must never stop a restaurant serving. What is synchronous is the
 * receipt row itself, written the moment payment is confirmed, so nothing can be
 * settled without leaving a record that it still needs a signature.
 */
class FiscalizationService
{
    public function __construct(
        private readonly ReceiptPayloadBuilder $payloads,
        private readonly FiskalyManagementClient $management,
    ) {}

    public function enabled(): bool
    {
        return (bool) config('services.fiskaly.enabled', false);
    }

    /** Whether this vendor's receipts have to be signed at all. */
    public function required(?Vendor $vendor): bool
    {
        if (! $this->enabled() || ! $vendor) {
            return false;
        }

        return in_array(
            $this->countryCode($vendor),
            (array) config('services.fiskaly.countries', []),
            true,
        );
    }

    /**
     * The vendor's country for fiscal purposes.
     *
     * During activation the country has not reached the vendor record yet — it
     * sits on the legal change awaiting approval, like the VAT number. Reading
     * only the vendor would make a brand-new Austrian restaurant look like it
     * needed no cash register at all.
     */
    public function countryCode(Vendor $vendor): string
    {
        $country = (string) ($vendor->country ?? '');

        if ($country === '') {
            $country = (string) (VendorRequestChange::where('vendor_id', $vendor->id)
                // Keep the country after a rejected first submission so the
                // activation wizard still knows which correction steps apply.
                // A rejected restaurant cannot go live, so this is only used
                // to route it back through the right setup flow.
                ->whereIn('status', ['pending', 'approved', 'rejected'])
                ->latest()
                ->value('country') ?? '');
        }

        return TaxCalculationService::countryCode($country);
    }

    /**
     * Called when an order's payment is confirmed. Records the receipt and
     * queues the signature. Never throws into the payment path: a receipt that
     * cannot be prepared is recorded as failed and surfaced, not bounced back at
     * a customer who has already paid.
     */
    public function onPaymentConfirmed(Order $order): void
    {
        try {
            $receipt = $this->record($order);

            if ($receipt && $receipt->state === FiscalReceipt::STATE_PENDING) {
                // The dispatch is inside the guard too: on a synchronous queue
                // driver the job runs right here, and a provider outage must
                // still not surface to a customer who has already paid.
                DeferredQueueDispatcher::dispatch(new FiscalizeOrder($receipt->id));
            }
        } catch (Throwable $exception) {
            Log::error('Could not fiscalize an order.', [
                'order_id' => $order->id,
                'vendor_id' => $order->vendor_id,
                'exception' => $exception,
            ]);
        }
    }

    /**
     * Build and persist the snapshot. Returns null when this vendor is not
     * fiscalized, and the existing row when the order already has one — an
     * order is signed once.
     */
    public function record(Order $order): ?FiscalReceipt
    {
        $order->loadMissing('vendor');

        if (! $this->required($order->vendor)) {
            return null;
        }

        $existing = FiscalReceipt::where('order_id', $order->id)->first();

        if ($existing) {
            return $existing;
        }

        $country = $this->countryCode($order->vendor);
        $payload = $this->payloads->build($order, $country);

        return FiscalReceipt::create([
            'order_id' => $order->id,
            'vendor_id' => $order->vendor_id,
            'provider' => 'fiskaly',
            'country' => $country,
            'invoice_number' => $order->invoice_number,
            'external_id' => (string) Str::uuid(),
            'state' => FiscalReceipt::STATE_PENDING,
            'payload' => $payload,
            'total_gross' => $payload['total_gross'],
            'currency' => $payload['currency'],
        ]);
    }

    /**
     * Sign a pending receipt. Safe to call again after a failure: fiskaly keys
     * both APIs on the id we chose, so a retry that reaches an already-signed
     * transaction returns it rather than creating a second one.
     */
    public function sign(FiscalReceipt $receipt): FiscalReceipt
    {
        if ($receipt->isSigned()) {
            return $receipt;
        }

        $receipt->increment('attempts');

        try {
            $device = $this->deviceFor($receipt);
            $result = $this->providerFor($receipt->country, $device)->sign($receipt, $device);

            $receipt->forceFill([
                'state' => FiscalReceipt::STATE_SIGNED,
                'response' => $result['response'],
                'qr_code_data' => $result['qr_code_data'],
                'signature' => $result['signature'],
                'signature_counter' => $result['signature_counter'],
                'receipt_number' => $result['receipt_number'],
                'register_serial_number' => $result['register_serial_number'],
                'signed_at' => now(),
                'last_error' => null,
            ])->save();

            return $receipt;
        } catch (Throwable $exception) {
            $receipt->forceFill([
                'state' => FiscalReceipt::STATE_FAILED,
                'last_error' => Str::limit($exception->getMessage(), 1000),
            ])->save();

            throw $exception;
        }
    }

    /**
     * Record the vendor's registration details without contacting fiskaly.
     *
     * Registration waits for a Tavlo admin, because the legal details it
     * depends on — the VAT number above all — only reach the vendor record when
     * an admin approves them. Registering earlier would file a signature unit
     * against a number nobody has checked.
     */
    public function submit(
        Vendor $vendor,
        array $credentials = [],
        ?string $country = null,
    ): FiscalDevice {
        $device = FiscalDevice::firstOrNew(['vendor_id' => $vendor->id]);

        $device->fill([
            'provider' => 'fiskaly',
            'country' => $country ?? $this->countryCode($vendor),
            'environment' => (string) config('services.fiskaly.environment', 'sandbox'),
            'state' => FiscalDevice::STATE_AWAITING_APPROVAL,
            'submitted_at' => now(),
            'last_error' => null,
        ]);

        if ($credentials !== []) {
            $device->credentials = [...($device->credentials ?? []), ...$credentials];
        }

        $device->save();

        return $device;
    }

    /**
     * Persist all identifiers and create the vendor's managed fiskaly Unit
     * before the legal approval transaction starts. This keeps remote ids and
     * the one-time Unit secret recoverable if a later SIGN request fails.
     *
     * @param  array<string, mixed>  $credentials
     * @param  array<string, mixed>  $legal
     */
    public function prepareForProvisioning(
        Vendor $vendor,
        array $credentials = [],
        array $legal = [],
    ): FiscalDevice {
        $country = TaxCalculationService::countryCode(
            (string) ($legal['country'] ?? $this->countryCode($vendor)),
        );
        $managedCountries = (array) config(
            'services.fiskaly.managed_organization_countries',
            ['AT', 'DE'],
        );
        $existing = FiscalDevice::where('vendor_id', $vendor->id)->first();

        if ($existing && ! $existing->needsRegistration()) {
            if ($legal !== [] && in_array($country, $managedCountries, true)) {
                return $this->management->ensureVendorOrganization($vendor, $existing, $legal);
            }

            return $existing;
        }

        $device = $this->submit($vendor, $credentials, $country);

        $device->forceFill([
            'signature_unit_id' => $device->signature_unit_id ?: (string) Str::uuid(),
            'register_id' => $device->register_id ?: (string) Str::uuid(),
            'serial_number' => $device->serial_number ?: 'TAVLO-'.$vendor->vendor_public_id,
        ])->save();

        if (in_array($country, $managedCountries, true)) {
            $device = $this->management->ensureVendorOrganization($vendor, $device, $legal);
        }

        return $device;
    }

    /**
     * Register a vendor whose legal details an admin has just approved.
     *
     * Returns null when there is nothing to do: an unfiscalized country, no
     * submission from the vendor yet, or a device already registered.
     */
    public function provisionOnApproval(Vendor $vendor): ?FiscalDevice
    {
        if (! $this->required($vendor)) {
            return null;
        }

        $device = FiscalDevice::where('vendor_id', $vendor->id)->first();

        // Already registered — a later legal change must not file a second till.
        if ($device && ! $device->needsRegistration()) {
            return null;
        }

        // Austria cannot register without the merchant's FinanzOnline
        // credentials, so it waits until the vendor has supplied them. Germany
        // needs nothing from the vendor and registers on approval alone.
        if ($this->needsMerchantCredentials($vendor)
            && blank($device?->credentials['fon_user_pin'] ?? null)) {
            return null;
        }

        return $this->provision($vendor);
    }

    /**
     * Whether registration needs something only the merchant can give us.
     *
     * Austria is the exception — its register is filed through the restaurant's
     * own FinanzOnline account. Everywhere else fiskaly provisions from our own
     * API key, so those vendors are registered on approval with nothing asked
     * of them.
     */
    public function needsMerchantCredentials(Vendor $vendor): bool
    {
        return $this->required($vendor) && in_array(
            $this->countryCode($vendor),
            (array) config('services.fiskaly.merchant_credential_countries', []),
            true,
        );
    }

    public function provision(Vendor $vendor, array $credentials = []): FiscalDevice
    {
        // Make sure the managed Unit, its encrypted key and our deterministic
        // SIGN ids exist before the first product call.
        $device = $this->prepareForProvisioning($vendor, $credentials);

        FiscalDevice::where('vendor_id', $vendor->id)
            ->update(['last_attempted_at' => now()]);

        $country = $this->countryCode($vendor);

        try {
            return $this->providerFor($country, $device->refresh())->provision($vendor, $credentials);
        } catch (Throwable $exception) {
            Log::error('Cash register registration failed.', [
                'vendor_id' => $vendor->id,
                'country' => $country,
                'context' => $exception instanceof FiscalizationException
                    ? $exception->context
                    : null,
                'exception' => $exception,
            ]);

            DB::table('fiscal_devices')
                ->where('vendor_id', $vendor->id)
                ->update([
                    'state' => FiscalDevice::STATE_FAILED,
                    // Store what an admin can act on, not the raw summary.
                    'last_error' => Str::limit(self::friendlyError($exception), 1000),
                    'updated_at' => now(),
                ]);

            throw $exception;
        }
    }

    public function providerFor(string $countryCode, ?FiscalDevice $device = null): FiscalProvider
    {
        $class = (string) config('services.fiskaly.providers.'.$countryCode, '');

        if ($class === '' || ! class_exists($class)) {
            throw new FiscalizationException(
                'No fiskaly provider is configured for this country.',
                ['country' => $countryCode, 'configured' => array_keys(
                    (array) config('services.fiskaly.providers', [])
                )],
            );
        }

        $baseUrl = (string) config('services.fiskaly.'.strtolower($countryCode).'.base_url', '');

        if ($baseUrl === '') {
            throw new FiscalizationException('No fiskaly base URL is configured for this country.', [
                'country' => $countryCode,
            ]);
        }

        $deviceCredentials = $device?->credentials ?? [];
        $apiKey = filled($deviceCredentials['fiskaly_api_key'] ?? null)
            ? (string) $deviceCredentials['fiskaly_api_key']
            : null;
        $apiSecret = filled($deviceCredentials['fiskaly_api_secret'] ?? null)
            ? (string) $deviceCredentials['fiskaly_api_secret']
            : null;
        $credentialScope = $apiKey ? ':'.hash('sha256', $apiKey) : ':legacy';

        $provider = new $class(new FiskalyClient(
            $baseUrl,
            'fiskaly:token:'.strtolower($countryCode).$credentialScope,
            $apiKey,
            $apiSecret,
        ));

        if (! $provider instanceof FiscalProvider) {
            throw new FiscalizationException('The configured fiskaly provider is not a FiscalProvider.', [
                'country' => $countryCode,
                'class' => $class,
            ]);
        }

        return $provider;
    }

    /**
     * fiskaly's own errors are aimed at integrators. Restaurant owners and the
     * admins chasing them get something they can act on, with the detail left
     * in the log.
     */
    public static function friendlyError(Throwable $exception): string
    {
        $summary = $exception instanceof FiscalizationException
            ? $exception->summary
            : $exception->getMessage();

        $detail = $exception instanceof FiscalizationException
            ? $exception->providerDetail()
            : null;

        if (str_contains($summary, 'FinanzOnline web-service credentials are required')) {
            return 'The FinanzOnline details are incomplete — all three values are needed.';
        }

        if (str_contains($summary, 'no VAT number')) {
            return 'A VAT number is required to register a cash register in Austria.';
        }

        if (str_contains($summary, 'credentials are not configured')) {
            return 'No fiskaly API credentials are configured on this environment.';
        }

        if (str_contains($summary, 'legal name, address, postal code')) {
            return 'Legal entity name, legal address, postal code, and city are required to register with fiskaly.';
        }

        if (str_contains($summary, 'one-time secret is unavailable')) {
            return $summary;
        }

        if (str_contains($summary, 'authentication failed')) {
            return 'Tavlo could not authenticate with fiskaly. Check FISKALY_API_KEY and FISKALY_API_SECRET.'
                .self::suffix($detail);
        }

        if (str_contains($summary, 'No fiskaly provider is configured')
            || str_contains($summary, 'No fiskaly base URL')) {
            return 'Tavlo does not support cash register registration in this country yet.';
        }

        if (str_contains($summary, 'rejected the request')) {
            // Whatever the provider said is the only thing that tells an admin
            // which field to send the restaurant back to.
            return 'fiskaly rejected the registration.'.self::suffix($detail)
                .($detail ? '' : ' Check the VAT number and the FinanzOnline details, then retry.');
        }

        return 'The cash register could not be registered.'.self::suffix($detail);
    }

    private static function suffix(?string $detail): string
    {
        return $detail ? ' '.Str::limit($detail, 300) : '';
    }

    private function deviceFor(FiscalReceipt $receipt): FiscalDevice
    {
        $device = FiscalDevice::where('vendor_id', $receipt->vendor_id)->first();

        if (! $device || ! $device->isUsable()) {
            throw new FiscalizationException(
                'This vendor has no initialized fiscal device. Run `php artisan fiskaly:provision`.',
                ['vendor_id' => $receipt->vendor_id, 'state' => $device?->state],
            );
        }

        return $device;
    }
}
