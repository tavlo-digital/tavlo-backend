<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Services\LocaleService;
use App\Services\NotificationTemplateService;
use App\Services\VendorDateTimeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(
        private readonly VendorDateTimeService $dateTimes,
        private readonly LocaleService $locales,
        private readonly NotificationTemplateService $templates,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $locale = $this->locales->resolveHeaderLocale($request);

        $notifications = Notification::where('customer_id', $request->user()->id)
            ->with([
                'vendor:id,country',
                'vendor.vendorSetting:id,vendor_id,date_format,time_format',
            ])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get(['id', 'vendor_id', 'event', 'message', 'metadata', 'read', 'created_at'])
            ->map(fn (Notification $notification) => [
                'id' => $notification->id,
                'event' => $notification->event,
                'message' => $this->templates->render($notification, $locale),
                'read' => $notification->read,
                'created_at' => $this->dateTimes->formatDateTime(
                    $notification->created_at,
                    $notification->vendor,
                ),
            ]);

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
