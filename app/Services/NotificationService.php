<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\TableScanSession;
use Illuminate\Support\Collection;

class NotificationService
{
    public static function notifyTableCustomers(int $restaurantTableId, string $event, string $message): void
    {
        $sessions = TableScanSession::query()
            ->where('restaurant_table_id', $restaurantTableId)
            ->where('status', 'active')
            ->get(['customer_id', 'vendor_id']);

        self::notifyCustomers(
            $sessions->pluck('customer_id')->filter()->unique(),
            $event,
            $message,
            $sessions->first()?->vendor_id,
        );
    }

    public static function notifyCustomers(
        Collection $customerIds,
        string $event,
        string $message,
        ?int $vendorId = null,
    ): void {
        $now = now();

        $rows = $customerIds->map(fn (int $id) => [
            'customer_id' => $id,
            'vendor_id' => $vendorId,
            'event' => $event,
            'message' => $message,
            'read' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        if (! empty($rows)) {
            Notification::insert($rows);
        }
    }

    public static function notify(string $role, int $id, string $event, string $message): void
    {
        Notification::create([
            "{$role}_id" => $id,
            'event' => $event,
            'message' => $message,
            'read' => false,
        ]);
    }
}
