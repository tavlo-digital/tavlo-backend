<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\GdprRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class PrivacyController extends Controller
{
    /**
     * Request data export (Right of Access).
     */
    public function requestDataExport(Request $request): JsonResponse
    {
        $customer = $request->user();

        // Check for existing pending request
        $pending = GdprRequest::where('customer_id', $customer->id)
            ->where('type', 'data_export')
            ->where('status', 'pending')
            ->first();

        if ($pending) {
            return response()->json([
                'message' => 'A data export request is already pending.',
            ], 422);
        }

        GdprRequest::create([
            'customer_id' => $customer->id,
            'type'        => 'data_export',
            'status'      => 'pending',
            'reason'      => 'Customer requested data export.',
        ]);

        return response()->json([
            'message' => 'Data export request submitted. You will receive a download link via email.',
        ], 201);
    }

    /**
     * Request account deletion (Right to Erasure).
     */
    public function requestAccountDeletion(Request $request): JsonResponse
    {
        $request->validate([
            'password'     => ['required', 'string'],
            'confirmation' => ['required', 'accepted'],
        ]);

        $customer = $request->user();

        if (! Hash::check($request->password, $customer->password)) {
            return response()->json([
                'message' => 'Incorrect password.',
            ], 422);
        }

        // Check for existing pending deletion
        $pending = GdprRequest::where('customer_id', $customer->id)
            ->where('type', 'account_deletion')
            ->where('status', 'pending')
            ->first();

        if ($pending) {
            return response()->json([
                'message' => 'An account deletion request is already pending.',
            ], 422);
        }

        GdprRequest::create([
            'customer_id' => $customer->id,
            'type'        => 'account_deletion',
            'status'      => 'pending',
            'reason'      => 'Customer requested account deletion.',
        ]);

        return response()->json([
            'message' => 'Account deletion request submitted. Your account will be permanently deleted after processing.',
        ], 201);
    }

    /**
     * Get GDPR request history.
     */
    public function gdprRequests(Request $request): JsonResponse
    {
        $requests = GdprRequest::where('customer_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->get();

        return response()->json($requests);
    }
}
