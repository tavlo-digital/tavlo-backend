<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Mail\FinanzOnlineInstructionsMail;
use App\Models\FiscalDevice;
use App\Models\Vendor;
use App\Models\VendorRequestChange;
use App\Services\Fiscal\FiscalizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

/**
 * Vendor-facing fiscalization setup: the "Connect your Registrierkasse" step of
 * activation. Registering a till with the tax office is a one-time action per
 * vendor, so everything here is idempotent and safe to retry.
 */
class FiscalController extends Controller
{
    public function __construct(private readonly FiscalizationService $fiscalization) {}

    /**
     * GET /api/vendor/{vendorId}/fiscal/status
     *
     * Drives the activation wizard: which steps apply, and which are done.
     */
    public function status(Request $request, string $vendorId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $this->authorizeVendor($request, $vendor);

        $required = $this->fiscalization->required($vendor);
        $country = $this->fiscalization->countryCode($vendor);
        $device = FiscalDevice::where('vendor_id', $vendor->id)->first();
        $legalSubmitted = $this->legalInfoSubmitted($vendor);
        $pendingFiscal = $this->pendingFiscalChange($vendor);
        $needsCredentials = $this->fiscalization->needsMerchantCredentials($vendor);

        // Germany asks nothing of the vendor: submitting their legal details is
        // the whole of their part, and registration follows on approval.
        $submitted = $needsCredentials
            ? ($pendingFiscal !== null || $device !== null)
            : $legalSubmitted;

        return response()->json([
            // Whether this vendor's country needs a registered cash register at
            // all. False for e.g. GB, and false while fiskaly is switched off.
            'required' => $required,
            'country' => $country,
            // Austria registers through FinanzOnline and needs the merchant's
            // web-service credentials. Germany needs nothing from the merchant.
            'needsFinanzOnline' => $this->fiscalization->needsMerchantCredentials($vendor),
            'connected' => (bool) $device?->isUsable(),
            'submitted' => $submitted,
            'awaitingApproval' => $pendingFiscal !== null,
            'state' => $device?->state
                ?? ($pendingFiscal ? FiscalDevice::STATE_AWAITING_APPROVAL : null),
            'serialNumber' => $device?->serial_number,
            'connectedAt' => $device?->initialized_at?->toISOString(),
            'lastError' => $device?->state === FiscalDevice::STATE_FAILED
                ? $device->last_error
                : null,
            'environment' => (string) config('services.fiskaly.environment', 'sandbox'),
            'legalInfoSubmitted' => $legalSubmitted,
            'vatNumber' => $this->resolveVatNumber($vendor),
            // The vendor has done everything asked of them. Registration itself
            // may still be waiting on an admin, which is not theirs to chase.
            'activationComplete' => $legalSubmitted && (! $required || $submitted),
            'needsMerchantAction' => $needsCredentials && ! $submitted,
        ]);
    }

    /**
     * POST /api/vendor/{vendorId}/fiscal/connect
     *
     * Registers the vendor's till at fiskaly and, for Austria, at the tax
     * office via FinanzOnline.
     */
    public function connect(Request $request, string $vendorId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $this->authorizeVendor($request, $vendor);

        if (! $this->fiscalization->required($vendor)) {
            return response()->json([
                'message' => 'Fiscalization does not apply to this restaurant.',
            ], 422);
        }

        $country = $this->fiscalization->countryCode($vendor);

        if (! $this->fiscalization->needsMerchantCredentials($vendor)) {
            return response()->json([
                'message' => 'Nothing is needed from you here — your cash register is set up once your legal details are approved.',
                'code' => 'NO_CREDENTIALS_REQUIRED',
            ], 422);
        }

        $data = $request->validate([
            // fiskaly's own schema bounds for a Teilnehmer-Identifikation.
            'fonParticipantId' => ['required', 'string', 'min:8', 'max:12'],
            // FinanzOnline's own rule for a web-service user.
            'fonUserId' => ['required', 'string', 'min:8', 'max:12', 'regex:/^(?=.*[A-Za-z])(?=.*\d)[A-Za-z0-9]+$/'],
            'fonUserPin' => ['required', 'string', 'min:8', 'max:128'],
        ], [
            'fonUserId.regex' => 'The Benutzer-ID must be 8–12 letters and digits, with at least one of each.',
            'fonParticipantId.min' => 'The Teilnehmer-Identifikation must be 8–12 characters.',
            'fonParticipantId.max' => 'The Teilnehmer-Identifikation must be 8–12 characters.',
        ]);

        if (! $this->legalInfoSubmitted($vendor)) {
            return response()->json([
                'message' => 'Add your legal and tax details before connecting a cash register.',
                'code' => 'LEGAL_INFO_REQUIRED',
            ], 422);
        }

        $vatNumber = $this->resolveVatNumber($vendor);

        if ($country === 'AT' && blank($vatNumber)) {
            return response()->json([
                'message' => 'A VAT number is required to register a cash register in Austria.',
                'code' => 'VAT_NUMBER_REQUIRED',
            ], 422);
        }

        // Cash register details are part of a vendor's legal identity, so they
        // join the same approval request as the rest of it. Adding or changing
        // them always goes back to a Tavlo admin — registration then happens on
        // approval, against details somebody has checked.
        $change = $this->upsertLegalChange($vendor, array_filter([
            'fon_participant_id' => $data['fonParticipantId'] ?? null,
            'fon_user_id' => $data['fonUserId'] ?? null,
            'fon_user_pin' => $data['fonUserPin'] ?? null,
        ]));

        $device = FiscalDevice::where('vendor_id', $vendor->id)->first();

        return response()->json([
            'submitted' => true,
            'connected' => (bool) $device?->isUsable(),
            'awaitingApproval' => true,
            'state' => $device?->state ?? FiscalDevice::STATE_AWAITING_APPROVAL,
            'country' => $country,
            'serialNumber' => $device?->serial_number,
            'connectedAt' => $device?->initialized_at?->toISOString(),
            'changeRequestId' => $change->id,
            'message' => 'Your details are with our team for review. We will register your cash register once they are approved.',
        ]);
    }

    /**
     * Merge the given fields into the vendor's pending legal change, opening one
     * carrying their current details if none is waiting.
     *
     * @param  array<string, mixed>  $fields
     */
    private function upsertLegalChange(Vendor $vendor, array $fields): VendorRequestChange
    {
        $pending = VendorRequestChange::where('vendor_id', $vendor->id)
            ->where('status', 'pending')
            ->latest()
            ->first();

        if ($pending) {
            $pending->update($fields);

            return $pending;
        }

        $vendor->loadMissing('vendorSetting');
        $latest = VendorRequestChange::where('vendor_id', $vendor->id)->latest()->first();

        return VendorRequestChange::create([
            'vendor_id' => $vendor->id,
            'restaurant_name' => $vendor->restaurant_name ?? $latest?->restaurant_name,
            'legal_entity_name' => $vendor->legal_entity_name ?? $latest?->legal_entity_name,
            'business_registration_number' => $vendor->business_registration_number
                ?? $latest?->business_registration_number,
            'vat_number' => $vendor->vat_number ?? $latest?->vat_number,
            'company_type' => $vendor->vendorSetting?->company_type ?? $latest?->company_type,
            'country' => $vendor->country ?? $latest?->country,
            'city' => $vendor->city ?? $latest?->city,
            'address' => $vendor->address ?? $latest?->address,
            'status' => 'pending',
            ...$fields,
        ]);
    }

    /**
     * POST /api/vendor/{vendorId}/fiscal/send-instructions
     *
     * Most owners forward this to their accountant rather than doing it in
     * FinanzOnline themselves.
     */
    public function sendInstructions(Request $request, string $vendorId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $this->authorizeVendor($request, $vendor);

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'locale' => ['nullable', 'string', Rule::in(['en', 'de'])],
        ]);

        Mail::to($data['email'])->send(new FinanzOnlineInstructionsMail(
            $vendor,
            $data['name'] ?? null,
            $data['locale'] ?? 'en',
        ));

        return response()->json([
            'message' => 'Instructions sent to '.$data['email'].'.',
        ]);
    }

    private function resolveVendor(string $vendorId): Vendor
    {
        return Vendor::where('vendor_public_id', $vendorId)
            ->when(ctype_digit($vendorId), fn ($query) => $query->orWhere('id', $vendorId))
            ->firstOrFail();
    }

    private function authorizeVendor(Request $request, Vendor $vendor): void
    {
        $user = $request->user();

        if ($user && $user->getTable() === 'vendors' && $user->id !== $vendor->id) {
            abort(403, 'Unauthorized');
        }
    }

    /**
     * The VAT number the vendor typed in step one is only written onto the
     * vendor once a Tavlo admin approves it, so fall back to the pending
     * request. See the note in the activation docs: on live this can register a
     * signature unit against a VAT number nobody has checked yet.
     */
    private function resolveVatNumber(Vendor $vendor): ?string
    {
        if (filled($vendor->vat_number)) {
            return $vendor->vat_number;
        }

        return VendorRequestChange::where('vendor_id', $vendor->id)
            ->whereIn('status', ['pending', 'approved'])
            ->latest()
            ->value('vat_number');
    }

    /** A pending change that carries cash register details, if there is one. */
    private function pendingFiscalChange(Vendor $vendor): ?VendorRequestChange
    {
        $pending = VendorRequestChange::where('vendor_id', $vendor->id)
            ->where('status', 'pending')
            ->latest()
            ->first();

        return $pending?->hasFiscalDetails() ? $pending : null;
    }

    private function legalInfoSubmitted(Vendor $vendor): bool
    {
        $vendor->loadMissing('vendorSetting');

        $onVendor = filled($vendor->legal_entity_name)
            && filled($vendor->business_registration_number)
            && filled($vendor->vat_number)
            && filled($vendor->country)
            && filled($vendor->address);

        if ($onVendor) {
            return true;
        }

        // A rejected first submission is not complete: the activation wizard
        // must return to the legal form so the restaurant can correct it.
        return VendorRequestChange::where('vendor_id', $vendor->id)
            ->whereIn('status', ['pending', 'approved'])
            ->exists();
    }
}
