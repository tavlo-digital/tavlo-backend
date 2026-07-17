<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Models\TeamMember;
use App\Services\StaffCommandBus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffCommandController extends Controller
{
    public function show(Request $request, string $commandId, StaffCommandBus $commands): JsonResponse
    {
        $actor = $request->user();
        if (! $actor instanceof TeamMember) {
            abort(403, 'Staff command status is only available to staff actors.');
        }

        $status = $commands->statusForActor($actor, $commandId);
        if (! $status) {
            abort(404, 'Staff command not found.');
        }

        return response()->json($status);
    }
}
