<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Services\SupabaseRealtimeTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class RealtimeTokenController extends Controller
{
    public function __invoke(Request $request, SupabaseRealtimeTokenService $tokens): JsonResponse
    {
        $customer = $request->user();

        $claims = [
            'actor_type' => 'customer',
            'actor_id' => (string) $customer->id,
            'customer_id' => (string) $customer->id,
        ];

        try {
            return response()->json($tokens->issue($claims));
        } catch (RuntimeException $exception) {
            report($exception);

            return response()->json([
                'message' => 'Realtime notifications are temporarily unavailable.',
            ], 503);
        }
    }
}
