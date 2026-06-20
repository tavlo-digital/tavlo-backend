<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Models\TeamMember;
use App\Models\Vendor;
use App\Services\SupabaseRealtimeTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class RealtimeTokenController extends Controller
{
    public function __invoke(Request $request, SupabaseRealtimeTokenService $tokens): JsonResponse
    {
        $actor = $request->user();

        if ($actor instanceof TeamMember) {
            $claims = [
                'actor_type' => 'team_member',
                'actor_role' => $actor->role,
                'actor_id' => (string) $actor->id,
                'vendor_id' => (string) $actor->vendor_id,
            ];
        } elseif ($actor instanceof Vendor) {
            $claims = [
                'actor_type' => 'vendor',
                'actor_role' => 'manager',
                'actor_id' => (string) $actor->id,
                'vendor_id' => (string) $actor->id,
            ];
        } else {
            abort(403);
        }

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
