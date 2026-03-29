<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
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
            'country'  => ['required', 'string', 'max:100'],
            'phone'    => ['required', 'string', 'max:30', 'unique:vendors,phone'],
            'email'    => ['required', 'email', 'max:255', 'unique:vendors,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $vendor = Vendor::create($validated);

        $token = $vendor->createToken('vendor-token', ['role:vendor'])->plainTextToken;

        return response()->json([
            'user'  => $vendor,
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

        if (! $vendor || ! Hash::check($request->password, $vendor->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token = $vendor->createToken('vendor-token', ['role:vendor'])->plainTextToken;

        return response()->json([
            'user'  => $vendor,
            'token' => $token,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => $request->user()]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }
}
