<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Api\Vendor\Concerns\QueuesStaffCommands;
use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\TeamMember;
use App\Services\LocaleService;
use App\Services\NotificationTemplateService;
use App\Services\StaffCommandBus;
use App\Services\VendorDateTimeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    use QueuesStaffCommands;

    public function __construct(
        private readonly VendorDateTimeService $dateTimes,
        private readonly LocaleService $locales,
        private readonly NotificationTemplateService $templates,
        private readonly StaffCommandBus $staffCommands,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $locale = $this->locales->resolveHeaderLocale($request);
        $query = $this->scopeFor($request);
        $notifications = (clone $query)
            ->where('is_silent', false)
            ->with(['vendor:id,country', 'vendor.vendorSetting:id,vendor_id,date_format,time_format'])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get(['id', 'vendor_id', 'event', 'message', 'metadata', 'read', 'created_at'])
            ->map(fn (Notification $notification) => [
                'id' => $notification->id,
                'event' => $notification->event,
                'message' => $this->templates->render($notification, $locale),
                'metadata' => $notification->metadata ?? [],
                'read' => $notification->read,
                'created_at' => $this->dateTimes->formatDateTime($notification->created_at, $notification->vendor),
            ]);

        $unreadCount = (clone $query)
            ->where('is_silent', false)
            ->where('read', false)
            ->count();

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
        ]);
    }

    public function markRead(Request $request, int $id): JsonResponse
    {
        if ($queued = $this->queuedStaffCommand(
            $request,
            $this->staffCommands,
            'notification.read',
            ['notification_id' => $id],
            [$this->staffNotificationResource($request)],
        )) {
            return $queued;
        }

        $notification = $this->scopeFor($request)->whereKey($id)->first();
        if (! $notification) {
            return response()->json(['message' => 'Notification not found.'], 404);
        }

        $notification->update(['read' => true]);

        return response()->json(['message' => 'Notification marked as read.']);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        if ($queued = $this->queuedStaffCommand(
            $request,
            $this->staffCommands,
            'notification.read_all',
            [],
            [$this->staffNotificationResource($request)],
        )) {
            return $queued;
        }

        $this->scopeFor($request)
            ->where('is_silent', false)
            ->where('read', false)
            ->update(['read' => true]);

        return response()->json(['message' => 'All notifications marked as read.']);
    }

    private function scopeFor(Request $request): Builder
    {
        $actor = $request->user();
        $query = Notification::query();

        if ($actor instanceof TeamMember && $actor->role === 'waiter') {
            return $query->where('waiter_id', $actor->id);
        }

        if ($actor instanceof TeamMember && $actor->role === 'kitchen') {
            return $query->where('kitchen_id', $actor->id);
        }

        return $query
            ->where('vendor_id', $actor->id)
            ->whereNull('customer_id')
            ->whereNull('waiter_id')
            ->whereNull('kitchen_id');
    }

    private function staffNotificationResource(Request $request): string
    {
        $actor = $request->user();
        if (! $actor instanceof TeamMember) {
            return "vendor:{$actor->id}:notifications";
        }

        return "vendor:{$actor->vendor_id}:actor:{$actor->role}:{$actor->id}:notifications";
    }
}
