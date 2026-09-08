<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Vendor\Concerns\GatesAnalyticsFeature;
use App\Models\FinancialExpense;
use App\Models\Vendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CRUD for vendor-entered "other costs" — see FinancialExpense's doc
 * comment. There is deliberately no index/list endpoint here: these rows
 * are surfaced through GET /vendor/{vendorId}/financial-reports itself
 * (FinancialReportService::manualExpenses()), the same single-source-of-
 * truth pattern every other section of that report already follows. The
 * frontend re-fetches the report after any create/update/delete here,
 * exactly like it already does for the "Try again" retry path.
 *
 * Attachments deliberately do NOT go through the app-wide MediaService /
 * `/media/{path}` public-disk convention every other upload (menu photos,
 * logos, avatars) uses. Receipts/invoices are real third-party business
 * documents (supplier names, addresses, tax numbers) — a materially more
 * sensitive class of file than a menu photo, and PublicMediaController's
 * `/media/*` route has no auth check at all plus a "single file in this
 * directory" fallback that serves a file back even when the requested
 * filename doesn't match (2026-09-08 audit finding: any vendor with
 * exactly one uploaded receipt was fetchable by an unauthenticated
 * attacker who simply guessed the numeric vendor id). Attachments here
 * instead live on the private `local` disk (storage/app/private — not
 * web-served by anything) and are only ever handed back through
 * showAttachment() below via a short-lived signed URL.
 */
class FinancialExpenseController extends Controller
{
    use GatesAnalyticsFeature;

    /** How long a signed attachment link stays valid once handed to the frontend. */
    private const ATTACHMENT_URL_TTL_MINUTES = 30;

    /**
     * POST /api/vendor/{vendorId}/financial-expenses
     */
    public function store(Request $request, string $vendorId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $this->authorizeVendor($request, $vendor);
        $this->authorizeFeatureAccess($vendor);

        $data = $this->validated($request, $vendor, isUpdate: false);

        $expense = $vendor->financialExpenses()->create($data);

        return response()->json(['data' => $this->format($expense)], 201);
    }

    /**
     * PUT /api/vendor/{vendorId}/financial-expenses/{expenseId}
     */
    public function update(Request $request, string $vendorId, int $expenseId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $this->authorizeVendor($request, $vendor);
        $this->authorizeFeatureAccess($vendor);

        $expense = $vendor->financialExpenses()->findOrFail($expenseId);
        $data = $this->validated($request, $vendor, isUpdate: true);

        // Attachment replaced or removed — delete the old file so orphans
        // don't pile up on the private disk.
        if (array_key_exists('attachment_path', $data) && $data['attachment_path'] !== $expense->attachment_path) {
            $this->deleteAttachment($expense->attachment_path);
        }

        $expense->update($data);

        return response()->json(['data' => $this->format($expense)]);
    }

    /**
     * DELETE /api/vendor/{vendorId}/financial-expenses/{expenseId}
     */
    public function destroy(Request $request, string $vendorId, int $expenseId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $this->authorizeVendor($request, $vendor);
        $this->authorizeFeatureAccess($vendor);

        $expense = $vendor->financialExpenses()->findOrFail($expenseId);
        $this->deleteAttachment($expense->attachment_path);
        $expense->delete();

        return response()->json(['message' => 'Deleted.']);
    }

    /**
     * POST /api/vendor/{vendorId}/financial-expenses/upload-attachment
     *
     * Same two-step "upload first, reference the path in the JSON
     * create/update call" convention as MenuItemController::uploadImage —
     * keeps this endpoint a plain multipart action instead of mixing file
     * uploads into the create/update JSON payload.
     */
    public function uploadAttachment(Request $request, string $vendorId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $this->authorizeVendor($request, $vendor);
        $this->authorizeFeatureAccess($vendor);

        $request->validate([
            'attachment' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ]);

        $file = $request->file('attachment');
        $path = $file->store("financial-expenses/{$vendor->id}/receipts", 'local');

        return response()->json([
            'attachmentPath' => $path,
            'attachmentUrl' => $this->attachmentUrl($path, $vendor->id),
            'attachmentOriginalName' => $file->getClientOriginalName(),
        ]);
    }

    /**
     * GET /api/vendor/{vendorId}/financial-expenses/attachment/{path}
     *
     * The only way an attachment's bytes are ever served — reachable only
     * via a short-lived signed URL minted by attachmentUrl() below, never a
     * bare guessable path. Deliberately outside the `auth:vendor` guard (see
     * routes/api/vendor.php): the frontend opens this in a new tab / as a
     * plain `<a href>`, which cannot attach a bearer token, so the signature
     * itself — proof that an authenticated request for THIS vendor+path
     * generated this exact URL, expiring after
     * self::ATTACHMENT_URL_TTL_MINUTES — is the auth. The vendor-prefix
     * check below is defense in depth on top of that signature, not a
     * substitute for it.
     */
    public function showAttachment(Request $request, string $vendorId, string $path): StreamedResponse
    {
        $vendor = $this->resolveVendor($vendorId);

        abort_unless($this->belongsToVendor($path, (int) $vendor->id), 404);
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path);
    }

    private function validated(Request $request, Vendor $vendor, bool $isUpdate): array
    {
        $required = $isUpdate ? 'sometimes' : 'required';

        $validator = Validator::make($request->all(), [
            'name' => [$required, 'string', 'max:255'],
            'category' => [$required, 'string', Rule::in(FinancialExpense::CATEGORIES)],
            'payee' => ['nullable', 'string', 'max:255'],
            'amount' => [$required, 'numeric', 'min:0.01', 'max:99999999.99'],
            'paymentMethod' => ['nullable', 'string', Rule::in(FinancialExpense::PAYMENT_METHODS)],
            'occurredAt' => [$required, 'date'],
            'description' => ['nullable', 'string', 'max:2000'],
            'isRecurring' => ['sometimes', 'boolean'],
            'recurrenceFrequency' => ['nullable', 'string', Rule::in(FinancialExpense::RECURRENCE_FREQUENCIES)],
            // attachmentPath must live under this vendor's own upload prefix
            // (see uploadAttachment() below) — otherwise a vendor could
            // point their expense at any file the private disk happens to
            // hold for another vendor and later have it permanently deleted
            // by deleteAttachment() when the attachment is replaced or the
            // expense is deleted (destroy()/update() both act on whatever
            // path is stored, with no ownership check of their own).
            'attachmentPath' => [
                'nullable', 'string', 'max:500',
                function (string $attribute, mixed $value, \Closure $fail) use ($vendor) {
                    if ($value === null || $value === '') {
                        return;
                    }

                    if (! $this->belongsToVendor($value, (int) $vendor->id)) {
                        $fail('The attachment does not belong to this vendor.');
                    }
                },
            ],
            'attachmentOriginalName' => ['nullable', 'string', 'max:255'],
        ]);

        $validator->sometimes(
            'recurrenceFrequency',
            ['required'],
            fn ($input) => (bool) $input->isRecurring,
        );

        $data = $validator->validate();

        $mapped = [];
        if (array_key_exists('name', $data)) {
            $mapped['name'] = $data['name'];
        }
        if (array_key_exists('category', $data)) {
            $mapped['category'] = $data['category'];
        }
        if (array_key_exists('payee', $data)) {
            $mapped['payee'] = $data['payee'];
        }
        if (array_key_exists('amount', $data)) {
            $mapped['amount'] = $data['amount'];
        }
        if (array_key_exists('paymentMethod', $data)) {
            $mapped['payment_method'] = $data['paymentMethod'];
        }
        if (array_key_exists('occurredAt', $data)) {
            $mapped['occurred_at'] = $data['occurredAt'];
        }
        if (array_key_exists('description', $data)) {
            $mapped['description'] = $data['description'];
        }
        if (array_key_exists('isRecurring', $data)) {
            $mapped['is_recurring'] = $data['isRecurring'];
            if (! $data['isRecurring']) {
                $mapped['recurrence_frequency'] = null;
            }
        }
        if (array_key_exists('recurrenceFrequency', $data) && ($data['isRecurring'] ?? true)) {
            $mapped['recurrence_frequency'] = $data['recurrenceFrequency'];
        }
        if (array_key_exists('attachmentPath', $data)) {
            $mapped['attachment_path'] = $data['attachmentPath'];
            $mapped['attachment_original_name'] = $data['attachmentOriginalName'] ?? null;
        }

        return $mapped;
    }

    private function format(FinancialExpense $expense): array
    {
        return [
            'id' => $expense->id,
            'name' => $expense->name,
            'category' => $expense->category,
            'payee' => $expense->payee,
            'amount' => round((float) $expense->amount, 2),
            'paymentMethod' => $expense->payment_method,
            'occurredAt' => $expense->occurred_at?->toIso8601String(),
            'description' => $expense->description,
            'isRecurring' => $expense->is_recurring,
            'recurrenceFrequency' => $expense->recurrence_frequency,
            'attachmentUrl' => $this->attachmentUrl($expense->attachment_path, (int) $expense->vendor_id),
            'attachmentOriginalName' => $expense->attachment_original_name,
        ];
    }

    /**
     * A time-limited signed URL for showAttachment() above, or null if there
     * is no attachment. Safe to regenerate on every response — the frontend
     * only ever needs a link valid for as long as the current page view.
     */
    private function attachmentUrl(?string $path, int $vendorId): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        // Named 'vendor.financial-expenses.attachment' once registered —
        // bootstrap/app.php applies a `vendor.` name prefix to every route
        // in routes/api/vendor.php.
        return URL::temporarySignedRoute(
            'vendor.financial-expenses.attachment',
            now()->addMinutes(self::ATTACHMENT_URL_TTL_MINUTES),
            ['vendorId' => $vendorId, 'path' => $path],
        );
    }

    /**
     * True only for a path both scoped under this vendor's own receipts
     * prefix AND free of any traversal segment. The prefix check alone is a
     * plain string match, not a path resolution — it does not by itself
     * reject a "../" segment that would resolve outside the vendor's own
     * prefix (e.g. "financial-expenses/{ownId}/receipts/../../{otherId}/receipts/x.pdf"
     * still starts with the right prefix as a string), hence the explicit
     * second check rather than relying on the prefix alone (2026-09-07 audit
     * finding). Shared by the attachmentPath validation rule and
     * showAttachment()'s own ownership check.
     */
    private function belongsToVendor(string $path, int $vendorId): bool
    {
        if (str_contains($path, '..') || str_contains($path, '\\')) {
            return false;
        }

        return str_starts_with($path, "financial-expenses/{$vendorId}/receipts/");
    }

    private function deleteAttachment(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        $disk = Storage::disk('local');
        if ($disk->exists($path)) {
            $disk->delete($path);
        }
    }

    private function resolveVendor(string $vendorId): Vendor
    {
        return Vendor::where('vendor_public_id', $vendorId)
            ->when(ctype_digit($vendorId), fn ($q) => $q->orWhere('id', $vendorId))
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
     * These are write endpoints with no payload to gracefully "lock" the way
     * FinancialReportController::index()/AnalyticsController's read routes
     * do — a vendor without access has no legitimate way to reach this UI in
     * the first place (the whole Financial Reports page shows the locked
     * preview instead), so hitting this endpoint without the feature only
     * happens by calling the API directly. A plain 403 matches
     * authorizeVendor()'s own guard-clause style above (2026-09-07 audit
     * finding — Financial Expenses previously had no plan gating at all).
     */
    private function authorizeFeatureAccess(Vendor $vendor): void
    {
        if (! $this->hasAnalyticsAccess($vendor)) {
            abort(403, 'Financial Reports is not included in your current plan.');
        }
    }
}
