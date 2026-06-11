<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Models\TeamMember;
use App\Models\Vendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'country'  => ['required', 'string', 'exists:countries,code'],
            'phone'    => ['required', 'string', 'max:30', 'unique:vendors,phone'],
            'email'    => ['required', 'email', 'max:255', 'unique:vendors,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $vendor = Vendor::create($validated);

        $token = $vendor->createToken('vendor-token', ['role:vendor', 'role:manager'])->plainTextToken;

        return response()->json([
            'user'  => $this->formatVendorUser($vendor),
            'token' => $token,
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $vendor = Vendor::where('email', $request->email)->first();

        if ($vendor && Hash::check($request->password, $vendor->password)) {
            $token = $vendor->createToken('vendor-token', ['role:vendor', 'role:manager'])->plainTextToken;

            return response()->json([
                'user'  => $this->formatVendorUser($vendor),
                'token' => $token,
            ]);
        }

        $member = TeamMember::with('vendor')
            ->where('email', $request->email)
            ->where('status', 'active')
            ->first();

        if (! $member || ! $member->password || ! Hash::check($request->password, $member->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token = $member->createToken('vendor-staff-token', [
            'role:team_member',
            "role:{$member->role}",
        ])->plainTextToken;

        return response()->json([
            'user'  => $this->formatTeamMemberUser($member),
            'token' => $token,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user instanceof TeamMember) {
            return response()->json(['data' => $this->formatTeamMemberUser($user->loadMissing('vendor'))]);
        }

        return response()->json(['data' => $this->formatVendorUser($user)]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password'         => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $request->user();

        if (! Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $user->update(['password' => $validated['password']]);

        return response()->json(['message' => 'Password changed successfully.']);
    }

    private function formatVendorUser(Vendor $vendor): array
    {
        $vendor->loadMissing('vendorSetting');

        return [
            'id'               => $vendor->id,
            'vendorId'         => (string) $vendor->id,
            'vendorPublicId'   => $vendor->vendor_public_id,
            'vendor_public_id' => $vendor->vendor_public_id,
            'actorType'        => 'vendor',
            'role'             => 'manager',
            'name'             => $vendor->name,
            'restaurantName'   => $vendor->restaurant_name,
            'country'          => $vendor->country,
            'phone'            => $vendor->phone,
            'email'            => $vendor->email,
            'permissions'      => ['*'],
            'created_at'       => $vendor->created_at?->toISOString(),
            'status'                => $vendor->status ?? 'pending',
            'liveStatus'            => $vendor->live_status ?? 'not-live',
            'isLiveAndDiscoverable' => (bool) ($vendor->vendorSetting?->is_live_and_discoverable ?? false),
        ];
    }

    private function formatTeamMemberUser(TeamMember $member): array
    {
        $vendor = $member->vendor;

        return [
            'id'             => $member->id,
            'vendorId'       => $vendor ? (string) $vendor->id : null,
            'vendorPublicId' => $vendor?->vendor_public_id,
            'vendor_public_id' => $vendor?->vendor_public_id,
            'actorType'      => 'team_member',
            'role'           => $member->role,
            'name'           => $member->name,
            'restaurantName' => $vendor?->restaurant_name,
            'country'        => $vendor?->country,
            'phone'          => null,
            'email'          => $member->email,
            'permissions'    => $member->permissions ?? [],
            'created_at'     => $member->created_at?->toISOString(),
        ];
    }
}
