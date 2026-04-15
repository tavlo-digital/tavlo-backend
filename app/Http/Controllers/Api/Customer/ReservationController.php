<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\Vendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ReservationController extends Controller
{
    /**
     * Get reservations grouped by status tab.
     */
    public function index(Request $request): JsonResponse
    {
        $customer = $request->user();
        $tab = $request->get('tab', 'upcoming'); // upcoming, pending, past, cancelled

        $query = Reservation::where('customer_id', $customer->id)
            ->with('vendor:id,vendor_public_id,restaurant_name,address,city');

        $query = match ($tab) {
            'upcoming'  => $query->where('status', 'confirmed')
                                 ->where('date', '>=', now()->toDateString())
                                 ->orderBy('date')->orderBy('time'),
            'pending'   => $query->where('status', 'pending')
                                 ->orderByDesc('created_at'),
            'past'      => $query->whereIn('status', ['confirmed', 'completed'])
                                 ->where('date', '<', now()->toDateString())
                                 ->orderByDesc('date'),
            'cancelled' => $query->where('status', 'cancelled')
                                 ->orderByDesc('updated_at'),
            default     => $query->orderByDesc('created_at'),
        };

        $reservations = $query->paginate($request->integer('per_page', 20));

        return response()->json($reservations);
    }

    /**
     * Create a new reservation.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'vendor_public_id' => ['required', 'string', 'exists:vendors,vendor_public_id'],
            'date'             => ['required', 'date', 'after_or_equal:today'],
            'time'             => ['required', 'date_format:H:i'],
            'party_size'       => ['required', 'integer', 'min:1', 'max:50'],
            'customer_note'    => ['nullable', 'string', 'max:500'],
        ]);

        $vendor = Vendor::where('vendor_public_id', $validated['vendor_public_id'])->firstOrFail();
        $customer = $request->user();

        $reservation = Reservation::create([
            'reservation_public_id' => 'res_' . Str::random(16),
            'vendor_id'             => $vendor->id,
            'customer_id'           => $customer->id,
            'guest_name'            => trim($customer->first_name . ' ' . $customer->last_name),
            'guest_email'           => $customer->email,
            'guest_phone'           => $customer->phone,
            'date'                  => $validated['date'],
            'time'                  => $validated['time'],
            'party_size'            => $validated['party_size'],
            'customer_note'         => $validated['customer_note'] ?? null,
            'status'                => 'pending',
        ]);

        return response()->json([
            'message'     => 'Reservation request submitted.',
            'reservation' => $reservation->load('vendor:id,vendor_public_id,restaurant_name'),
        ], 201);
    }

    /**
     * Show a single reservation.
     */
    public function show(Request $request, string $reservationPublicId): JsonResponse
    {
        $reservation = Reservation::where('reservation_public_id', $reservationPublicId)
            ->where('customer_id', $request->user()->id)
            ->with('vendor:id,vendor_public_id,restaurant_name,address,city,phone')
            ->firstOrFail();

        return response()->json($reservation);
    }

    /**
     * Cancel a reservation (customer can cancel pending or upcoming).
     */
    public function cancel(Request $request, string $reservationPublicId): JsonResponse
    {
        $reservation = Reservation::where('reservation_public_id', $reservationPublicId)
            ->where('customer_id', $request->user()->id)
            ->whereIn('status', ['pending', 'confirmed'])
            ->firstOrFail();

        $reservation->update(['status' => 'cancelled']);

        return response()->json([
            'message'     => 'Reservation cancelled.',
            'reservation' => $reservation,
        ]);
    }
}
