<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\SocialAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        protected SocialAuthService $socialAuthService,
    ) {}

    /**
     * Register a new customer (email/password).
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'first_name'            => ['required', 'string', 'max:255'],
            'last_name'             => ['required', 'string', 'max:255'],
            'phone'                 => ['required', 'string', 'max:30', 'unique:customers,phone'],
            'email'                 => ['required', 'email', 'max:255', 'unique:customers,email'],
            'password'              => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $customer = Customer::create([
            'customer_public_id' => 'cust_' . Str::random(16),
            'first_name'         => $validated['first_name'],
            'last_name'          => $validated['last_name'],
            'phone'              => $validated['phone'],
            'email'              => $validated['email'],
            'password'           => $validated['password'],
            'account_type'       => 'registered',
            'registration_source' => 'email',
        ]);

        $token = $customer->createToken('customer-token', ['role:customer'])->plainTextToken;

        return response()->json([
            'user'  => $customer,
            'token' => $token,
        ], 201);
    }

    /**
     * Register or sign in via social provider (Google / Apple / Facebook).
     * Verifies the access_token server-side with the provider.
     */
    public function socialRegister(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'provider'     => ['required', 'string', 'in:google,apple,facebook'],
            'access_token' => ['required', 'string'],
            'first_name'   => ['nullable', 'string', 'max:255'],
            'last_name'    => ['nullable', 'string', 'max:255'],
            'phone'        => ['nullable', 'string', 'max:30'],
        ]);

        try {
            $socialUser = $this->socialAuthService->verify(
                $validated['provider'],
                $validated['access_token'],
            );
        } catch (\Exception $e) {
            throw ValidationException::withMessages([
                'access_token' => [$e->getMessage()],
            ]);
        }

        $customer = Customer::where('social_provider', $validated['provider'])
            ->where('social_provider_id', $socialUser['provider_id'])
            ->first();

        if (! $customer) {
            $customer = Customer::where('email', $socialUser['email'])->first();

            if ($customer) {
                $customer->update([
                    'social_provider'    => $validated['provider'],
                    'social_provider_id' => $socialUser['provider_id'],
                ]);
            } else {
                $firstName = $socialUser['first_name'] ?: ($validated['first_name'] ?? '');
                $lastName = $socialUser['last_name'] ?: ($validated['last_name'] ?? '');

                $customer = Customer::create([
                    'customer_public_id'  => 'cust_' . Str::random(16),
                    'first_name'          => $firstName,
                    'last_name'           => $lastName,
                    'email'               => $socialUser['email'],
                    'phone'               => $validated['phone'] ?? '',
                    'password'            => Hash::make(Str::random(32)),
                    'social_provider'     => $validated['provider'],
                    'social_provider_id'  => $socialUser['provider_id'],
                    'account_type'        => 'registered',
                    'registration_source' => $validated['provider'],
                    'email_verified_at'   => now(),
                ]);
            }
        }

        $customer->tokens()->delete();
        $token = $customer->createToken('customer-token', ['role:customer'])->plainTextToken;

        return response()->json([
            'user'  => $customer,
            'token' => $token,
        ], 200);
    }

    /**
     * Login as a guest customer.
     *
     * Creates a brand-new customer with system-generated email/phone/password
     * and an `account_type = guest`. The caller may optionally provide a
     * `first_name` (and `last_name`) — otherwise a default "Guest" name is used.
     * Returns the same shape as register/login: `{ user, token }`.
     */
    public function loginAsGuest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name'  => ['nullable', 'string', 'max:255'],
        ]);

        $suffix = strtolower(Str::random(10));

        $customer = Customer::create([
            'customer_public_id'  => 'cust_' . Str::random(16),
            'first_name'          => $validated['first_name'] ?? 'Guest',
            'last_name'           => $validated['last_name'] ?? Str::upper(Str::random(random_int(5, 6))),
            'phone'               => 'guest-' . $suffix,
            'email'               => 'guest_' . $suffix . '@tavlo.guest',
            'password'            => Hash::make(Str::random(32)),
            'account_type'        => 'guest',
            'registration_source' => 'guest',
        ]);

        $token = $customer->createToken('customer-token', ['role:customer'])->plainTextToken;

        return response()->json([
            'user'  => $customer,
            'token' => $token,
        ], 201);
    }

    /**
     * Login with email and password.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $customer = Customer::where('email', $request->email)->first();

        if (! $customer || ! Hash::check($request->password, $customer->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $customer->update(['last_active_at' => now()]);

        $token = $customer->createToken('customer-token', ['role:customer'])->plainTextToken;

        return response()->json([
            'user'  => $customer,
            'token' => $token,
        ]);
    }

    /**
     * Login via social provider (Google / Apple / Facebook).
     * Verifies the access_token server-side with the provider.
     */
    public function socialLogin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'provider'     => ['required', 'string', 'in:google,apple,facebook'],
            'access_token' => ['required', 'string'],
        ]);

        try {
            $socialUser = $this->socialAuthService->verify(
                $validated['provider'],
                $validated['access_token'],
            );
        } catch (\Exception $e) {
            throw ValidationException::withMessages([
                'access_token' => [$e->getMessage()],
            ]);
        }

        $customer = Customer::where('social_provider', $validated['provider'])
            ->where('social_provider_id', $socialUser['provider_id'])
            ->first();

        if (! $customer) {
            throw ValidationException::withMessages([
                'provider' => ['No account found for this social provider. Please register first.'],
            ]);
        }

        $customer->update(['last_active_at' => now()]);

        $customer->tokens()->delete();
        $token = $customer->createToken('customer-token', ['role:customer'])->plainTextToken;

        return response()->json([
            'user'  => $customer,
            'token' => $token,
        ]);
    }

    /**
     * Get the authenticated customer.
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json($request->user());
    }

    /**
     * Log out the current session.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    /**
     * Log out from all devices.
     */
    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return response()->json(['message' => 'Logged out from all devices.']);
    }
}
