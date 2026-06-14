<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Promotion;
use App\Models\Vendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PromotionController extends Controller
{
    private function resolveVendor(string $vendorId): Vendor
    {
        return Vendor::where('vendor_public_id', $vendorId)->firstOrFail();
    }

    private function authorizeVendor(Request $request, Vendor $vendor): void
    {
        if ((string) $request->user()->id !== (string) $vendor->id) {
            abort(403, 'Unauthorized');
        }
    }

    /** GET /api/vendor/{vendorId}/promotions */
    public function index(Request $request, string $vendorId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $promotions = Promotion::where('vendor_id', $vendor->id)
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $promotions]);
    }

    /** POST /api/vendor/{vendorId}/promotions */
    public function store(Request $request, string $vendorId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $this->authorizeVendor($request, $vendor);

        $data = $request->validate([
            'name'           => ['required', 'string', 'max:255'],
            'type'           => ['required', 'string', 'in:happy-hour,weekend-special,item-discount'],
            'description'    => ['required', 'string', 'max:1000'],
            'discount_type'  => ['required', 'string', 'in:percentage,fixed'],
            'discount_value' => ['required', 'numeric', 'min:0.01'],
            'start_date'     => ['required', 'date'],
            'end_date'       => ['required', 'date', 'after_or_equal:start_date'],
            'start_time'     => ['nullable', 'date_format:H:i'],
            'end_time'       => ['nullable', 'date_format:H:i'],
            'active_days'    => ['nullable', 'array'],
            'active_days.*'  => ['string', 'in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday'],
            'is_active'      => ['sometimes', 'boolean'],
        ]);

        $promotion = Promotion::create(array_merge($data, ['vendor_id' => $vendor->id]));

        return response()->json($promotion, 201);
    }

    /** PATCH /api/vendor/{vendorId}/promotions/{promotionId} */
    public function update(Request $request, string $vendorId, int $promotionId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $this->authorizeVendor($request, $vendor);
        $promotion = Promotion::where('vendor_id', $vendor->id)->findOrFail($promotionId);

        $data = $request->validate([
            'name'           => ['sometimes', 'string', 'max:255'],
            'type'           => ['sometimes', 'string', 'in:happy-hour,weekend-special,item-discount'],
            'description'    => ['sometimes', 'string', 'max:1000'],
            'discount_type'  => ['sometimes', 'string', 'in:percentage,fixed'],
            'discount_value' => ['sometimes', 'numeric', 'min:0.01'],
            'start_date'     => ['sometimes', 'date'],
            'end_date'       => ['sometimes', 'date'],
            'start_time'     => ['nullable', 'date_format:H:i'],
            'end_time'       => ['nullable', 'date_format:H:i'],
            'active_days'    => ['nullable', 'array'],
            'active_days.*'  => ['string', 'in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday'],
            'is_active'      => ['sometimes', 'boolean'],
        ]);

        $promotion->update($data);

        return response()->json($promotion);
    }

    /** DELETE /api/vendor/{vendorId}/promotions/{promotionId} */
    public function destroy(Request $request, string $vendorId, int $promotionId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $this->authorizeVendor($request, $vendor);
        $promotion = Promotion::where('vendor_id', $vendor->id)->findOrFail($promotionId);
        $promotion->delete();

        return response()->json(['message' => 'Promotion deleted.']);
    }

    /** PATCH /api/vendor/{vendorId}/promotions/{promotionId}/toggle */
    public function toggle(Request $request, string $vendorId, int $promotionId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $this->authorizeVendor($request, $vendor);
        $promotion = Promotion::where('vendor_id', $vendor->id)->findOrFail($promotionId);
        $promotion->update(['is_active' => ! $promotion->is_active]);

        return response()->json($promotion);
    }
}
