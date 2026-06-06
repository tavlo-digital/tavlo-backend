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
            'customer_id' => $id,
            'event'       => $event,
            'message'     => $message,
            'read'        => false,
            'created_at'  => now(),
            'updated_at'  => now(),
        ])->all();

        if (! empty($rows)) {
            Notification::insert($rows);
        }
    }

    public static function notify(string $role, int $id, string $event, string $message): void
    {
        Notification::create([
            "{$role}_id" => $id,
            'event'      => $event,
            'message'    => $message,
            'read'       => false,
        ]);
    }
}
