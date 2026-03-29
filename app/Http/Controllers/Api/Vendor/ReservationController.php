<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\Vendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ReservationController extends Controller
{
    /**
     * GET /api/vendor/{vendorId}/reservations?status=pending&date=2026-03-28
     */
    public function index(Request $request, string $vendorId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);

        $query = $vendor->reservations()->with('customer:id,name,email,phone');

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('date')) {
            $query->whereDate('date', $request->query('date'));
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
        $reservation = Reservation::where('reservation_public_id', $reservationId)
            ->orWhere('id', $reservationId)
            ->firstOrFail();

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
        return [
            'id' => (string) $r->id,
            'reservationPublicId' => $r->reservation_public_id,
            'customer' => $r->customer ? [
                'id' => (string) $r->customer->id,
                'name' => $r->customer->name,
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
            ->orWhere('id', $vendorId)
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
