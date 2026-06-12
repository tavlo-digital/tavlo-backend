<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notifications = Notification::where('customer_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get(['id', 'event', 'message', 'read', 'created_at']);

        $unreadCount = Notification::where('customer_id', $request->user()->id)
            ->where('read', false)
            ->count();

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
        ]);
    }

    public function markRead(Request $request, int $id): JsonResponse
    {
        $notification = Notification::where('id', $id)
            ->where('customer_id', $request->user()->id)
            ->first();

        if (! $notification) {
            return response()->json(['message' => 'Notification not found.'], 404);
        }

        $notification->update(['read' => true]);

        return response()->json(['message' => 'Notification marked as read.']);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        Notification::where('customer_id', $request->user()->id)
            ->where('read', false)
            ->update(['read' => true]);

        return response()->json(['message' => 'All notifications marked as read.']);
    }
}
