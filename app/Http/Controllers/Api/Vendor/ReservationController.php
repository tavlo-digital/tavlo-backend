<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\Vendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReservationController extends Controller
{
    /**
     * GET /api/vendor/{vendorId}/reservations?status=pending&date=2026-03-28
     */
    public function index(Request $request, string $vendorId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $this->authorizeVendor($request, $vendor);

        $filters = $request->validate([
            'status' => ['nullable', 'string', 'in:pending,confirmed,cancelled,completed,no_show'],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $query = $vendor->reservations()->with('customer:id,first_name,last_name,email,phone');

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['date'])) {
            $query->whereDate('date', $filters['date']);
        }

        $reservations = $query->orderBy('date')
            ->orderBy('time')
            ->get()
            ->map(fn (Reservation $r) => $this->formatReservation($r));

        return response()->json($reservations);
    }

    /**
     * PATCH /api/reservations/{reservationId}/status
     */
    public function updateStatus(Request $request, string $reservationId): JsonResponse
    {
        $query = Reservation::where('reservation_public_id', $reservationId);

        if (ctype_digit($reservationId)) {
            $query->orWhere('id', $reservationId);
        }

        $reservation = $query->firstOrFail();

        $this->authorizeVendor($request, $reservation->vendor);

        $data = $request->validate([
            'status' => ['required', 'string', 'in:pending,confirmed,cancelled,completed,no_show'],
            'vendorNote' => ['nullable', 'string', 'max:1000'],
        ]);

        $reservation->update([
            'status' => $data['status'],
            'vendor_note' => $data['vendorNote'] ?? $reservation->vendor_note,
        ]);

        return response()->json($this->formatReservation($reservation->fresh()->load('customer')));
    }

    // ----------------------------------------------------------------

    private function formatReservation(Reservation $r): array
    {
        $customerName = $r->customer
            ? trim(implode(' ', array_filter([$r->customer->first_name, $r->customer->last_name])))
            : '';

        return [
            'id' => (string) $r->id,
            'reservationPublicId' => $r->reservation_public_id,
            'customer' => $r->customer ? [
                'id' => (string) $r->customer->id,
                'name' => $customerName !== '' ? $customerName : ($r->guest_name ?? 'Guest'),
                'email' => $r->customer->email,
                'phone' => $r->customer->phone,
            ] : null,
            'guestName' => $r->guest_name,
            'guestEmail' => $r->guest_email,
            'guestPhone' => $r->guest_phone,
            'date' => $r->date->format('Y-m-d'),
            'time' => $r->time,
            'partySize' => $r->party_size,
            'status' => $r->status,
            'customerNote' => $r->customer_note,
            'vendorNote' => $r->vendor_note,
            'tableNumber' => $r->table_number,
            'createdAt' => $r->created_at->toISOString(),
        ];
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
}
