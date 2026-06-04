<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\TableScanSession;
use Illuminate\Support\Collection;

class NotificationService
{
    public static function notifyTableCustomers(int $restaurantTableId, string $event, string $message): void
    {
        $customerIds = TableScanSession::query()
            ->where('restaurant_table_id', $restaurantTableId)
            ->where('status', 'active')
            ->distinct()
            ->pluck('customer_id')
            ->filter();

        self::notifyCustomers($customerIds, $event, $message);
    }

    public static function notifyCustomers(Collection $customerIds, string $event, string $message): void
    {
        $rows = $customerIds->map(fn (int $id) => [
            'user_id'    => $id,
            'event'      => $event,
            'message'    => $message,
            'read'       => false,
            'user_role'  => 'customer',
            'created_at' => now(),
            'updated_at' => now(),
        ])->all();

        if (! empty($rows)) {
            Notification::insert($rows);
        }
    }

    public static function notify(int $userId, string $userRole, string $event, string $message): void
    {
        Notification::create([
            'user_id'   => $userId,
            'event'     => $event,
            'message'   => $message,
            'read'      => false,
            'user_role' => $userRole,
        ]);
    }
}
