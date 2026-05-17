<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    /**
     * Get profile overview with recent activity.
     */
    public function show(Request $request): JsonResponse
    {
        $customer = $request->user();
        $profile = $customer->toArray();
        $profile['monthly_orders'] = $customer->orders()
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->count();
        $profile['orders_count'] = $customer->orders()->count();

        $recentVendors = $customer->orders()
            ->with('vendor:id,vendor_public_id,restaurant_name,slug')
            ->select('vendor_id', 'created_at')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->unique('vendor_id')
            ->take(5)
            ->map(fn ($order) => $order->vendor)
            ->values();

        $loyaltyOverview = $customer->loyaltyPoints()
            ->with('vendor:id,vendor_public_id,restaurant_name')
            ->get();

        return response()->json([
            'profile'           => $profile,
            'recent_restaurants' => $recentVendors,
            'loyalty_overview'  => $loyaltyOverview,
        ]);
    }

    /**
     * Update editable profile fields.
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'first_name'      => ['nullable', 'string', 'max:255'],
            'last_name'       => ['nullable', 'string', 'max:255'],
            'gender'          => ['nullable', 'string', 'in:male,female,other,prefer_not_to_say'],
            'date_of_birth'   => ['nullable', 'date', 'before:today'],
            'address'         => ['nullable', 'string', 'max:500'],
            'profile_picture' => ['nullable', 'string', 'max:500'],
        ]);

        $request->user()->update($validated);

        return response()->json([
            'message' => 'Profile updated.',
            'user'    => $request->user()->fresh(),
        ]);
    }

    /**
     * Change phone number.
     */
    public function changePhone(Request $request): JsonResponse
    {
        $customer = $request->user();

        $validated = $request->validate([
            'new_number' => [
                'required',
                'string',
                'max:30',
                Rule::unique('customers', 'phone')->ignore($customer->id),
            ],
        ]);

        $updates = ['phone' => $validated['new_number']];
        if ($validated['new_number'] !== $customer->phone) {
            $updates['phone_verified'] = false;
        }

        $customer->update($updates);

        return response()->json([
            'message' => 'Phone number updated.',
            'user'    => $customer->fresh(),
        ]);
    }

    /**
     * Change email address.
     */
    public function changeEmail(Request $request): JsonResponse
    {
        $customer = $request->user();

        $validated = $request->validate([
            'current_email' => ['required', 'email'],
            'new_email'     => [
                'required',
                'email',
                'max:255',
                Rule::unique('customers', 'email')->ignore($customer->id),
            ],
        ]);

        if (strtolower($validated['current_email']) !== strtolower($customer->email)) {
            throw ValidationException::withMessages([
                'current_email' => ['The current email address is incorrect.'],
            ]);
        }

        $updates = ['email' => $validated['new_email']];
        if (strtolower($validated['new_email']) !== strtolower($customer->email)) {
            $updates['email_verified_at'] = null;
        }

        $customer->update($updates);

        return response()->json([
            'message' => 'Email address updated.',
            'user'    => $customer->fresh(),
        ]);
    }

    /**
     * Change password.
     */
    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => ['required', 'string'],
            'password'         => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $customer = $request->user();

        if (! Hash::check($request->current_password, $customer->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $customer->update(['password' => $request->password]);

        return response()->json(['message' => 'Password changed successfully.']);
    }
}
