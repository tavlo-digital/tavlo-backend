<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
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

        $customerId = (int) $request->user('customer')->id;
        $expectedChannel = "private-customer.{$customerId}";
        if (! hash_equals($expectedChannel, $data['channel_name'])) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $key = (string) config('broadcasting.connections.reverb.key');
        $secret = (string) config('broadcasting.connections.reverb.secret');
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
