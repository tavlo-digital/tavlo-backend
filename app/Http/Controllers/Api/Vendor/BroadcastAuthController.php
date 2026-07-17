<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Models\TeamMember;
use App\Models\Vendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BroadcastAuthController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = Validator::make($request->all(), [
            'socket_id' => ['required', 'string', 'max:100', 'regex:/^\d+\.\d+$/'],
            'channel_name' => ['required', 'string', 'max:200'],
        ])->validate();

        $actor = $request->user();
        $expectedChannel = match (true) {
            $actor instanceof Vendor => "private-vendor.{$actor->id}",
            $actor instanceof TeamMember && in_array($actor->role, ['waiter', 'kitchen'], true) => "private-{$actor->role}.{$actor->id}",
            default => null,
        };

        if ($expectedChannel === null || ! hash_equals($expectedChannel, $data['channel_name'])) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $connection = (string) config('broadcasting.default');
        if ($connection !== 'pusher') {
            return response()->json(['message' => 'Operational realtime is not configured.'], 503);
        }

        $key = (string) config('broadcasting.connections.pusher.key');
        $secret = (string) config('broadcasting.connections.pusher.secret');
        if ($key === '' || $secret === '') {
            return response()->json(['message' => 'Realtime is not configured.'], 503);
        }

        $signature = hash_hmac(
            'sha256',
            "{$data['socket_id']}:{$data['channel_name']}",
            $secret,
        );

        return response()->json(['auth' => "{$key}:{$signature}"]);
    }
}
