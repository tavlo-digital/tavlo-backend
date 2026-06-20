<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\MenuItem;
use App\Models\Notification;
use App\Models\NotificationTemplate;
use App\Models\RestaurantTable;
use App\Models\Vendor;

class NotificationTemplateService
{
    public const TEMPLATES = [
        'cart.item_added' => [
            'event' => 'cart_updated',
            'label' => 'Cart item added',
            'default' => '{customer_name} added {item_name} to the cart.',
            'placeholders' => ['customer_name', 'item_name'],
        ],
        'cart.item_updated' => [
            'event' => 'cart_updated',
            'label' => 'Cart item updated',
            'default' => '{customer_name} updated {item_name} in the cart.',
            'placeholders' => ['customer_name', 'item_name'],
        ],
        'cart.item_removed' => [
            'event' => 'cart_updated',
            'label' => 'Cart item removed',
            'default' => '{customer_name} removed {item_name} from the cart.',
            'placeholders' => ['customer_name', 'item_name'],
        ],
        'cart.item_preparing' => [
            'event' => 'cart_item_updated',
            'label' => 'Cart item preparing',
            'default' => '{item_name} is now being prepared.',
            'placeholders' => ['item_name'],
        ],
        'cart.item_ready' => [
            'event' => 'cart_item_updated',
            'label' => 'Cart item ready',
            'default' => '{item_name} is ready.',
            'placeholders' => ['item_name'],
        ],
        'cart.item_served' => [
            'event' => 'cart_item_updated',
            'label' => 'Cart item served',
            'default' => '{item_name} has been served.',
            'placeholders' => ['item_name'],
        ],
        'cart.item_status_updated' => [
            'event' => 'cart_item_updated',
            'label' => 'Cart item status updated',
            'default' => '{item_name} has been updated.',
            'placeholders' => ['item_name'],
        ],
        'order.status_updated' => [
            'event' => 'order_updated',
            'label' => 'Order status updated',
            'default' => 'Your order status has been updated.',
            'placeholders' => [],
        ],
        'order.waiter_confirmed' => [
            'event' => 'order_updated',
            'label' => 'Order confirmed by waiter',
            'default' => 'Your order has been confirmed by the waiter.',
            'placeholders' => [],
        ],
        'order.ready' => [
            'event' => 'order_updated',
            'label' => 'Order ready',
            'default' => 'Your order is ready!',
            'placeholders' => [],
        ],
        'order.picked_up' => [
            'event' => 'order_updated',
            'label' => 'Order picked up',
            'default' => 'Your order has been picked up.',
            'placeholders' => [],
        ],
        'order.served' => [
            'event' => 'order_updated',
            'label' => 'Order served',
            'default' => 'Your order has been served. Enjoy!',
            'placeholders' => [],
        ],
        'order.cancelled' => [
            'event' => 'order_updated',
            'label' => 'Order cancelled',
            'default' => 'Your order has been cancelled.',
            'placeholders' => [],
        ],
        'order.draft_created' => [
            'event' => 'order_updated',
            'label' => 'Order draft created',
            'default' => '{customer_name} created an order draft.',
            'placeholders' => ['customer_name'],
        ],
        'order.draft_updated' => [
            'event' => 'order_updated',
            'label' => 'Order draft updated',
            'default' => '{customer_name} updated their order draft.',
            'placeholders' => ['customer_name'],
        ],
        'order.sharing_updated' => [
            'event' => 'order_updated',
            'label' => 'Order sharing updated',
            'default' => '{customer_name} updated item sharing on the order.',
            'placeholders' => ['customer_name'],
        ],
        'order.confirmed' => [
            'event' => 'order_updated',
            'label' => 'Order confirmed',
            'default' => '{customer_name} confirmed their order.',
            'placeholders' => ['customer_name'],
        ],
        'payment.initiated' => [
            'event' => 'payment_updated',
            'label' => 'Payment initiated',
            'default' => '{customer_name} initiated a payment.',
            'placeholders' => ['customer_name'],
        ],
        'payment.updated' => [
            'event' => 'payment_updated',
            'label' => 'Payment updated',
            'default' => '{customer_name} updated the payment.',
            'placeholders' => ['customer_name'],
        ],
        'payment.completed' => [
            'event' => 'payment_updated',
            'label' => 'Payment completed',
            'default' => 'A payment has been completed on this table.',
            'placeholders' => [],
        ],
        'payment.cash_confirmed' => [
            'event' => 'payment_updated',
            'label' => 'Cash payment confirmed',
            'default' => 'Your cash payment has been confirmed.',
            'placeholders' => [],
        ],
        'participant.added' => [
            'event' => 'participant_added',
            'label' => 'Participant added',
            'default' => '{customer_name} has joined the table.',
            'placeholders' => ['customer_name'],
        ],
        'session.closed' => [
            'event' => 'session_expire',
            'label' => 'Session closed',
            'default' => 'Your table session has been closed.',
            'placeholders' => [],
        ],
        'session.expired' => [
            'event' => 'session_expire',
            'label' => 'Session expired',
            'default' => 'Your table session has expired.',
            'placeholders' => [],
        ],
        'table.call' => [
            'event' => 'table_call',
            'label' => 'Table call',
            'default' => 'Table {table_label} is calling.',
            'placeholders' => ['table_label'],
        ],
        'staff.order_confirmed' => [
            'event' => 'order_confirmed',
            'label' => 'Staff: new confirmed order',
            'default' => 'New order #{order_number} for Table {table_label}.',
            'placeholders' => ['order_number', 'table_label'],
        ],
        'staff.order_ready' => [
            'event' => 'order_ready',
            'label' => 'Staff: order ready',
            'default' => 'Order #{order_number} for Table {table_label} is ready.',
            'placeholders' => ['order_number', 'table_label'],
        ],
        'staff.item_ready' => [
            'event' => 'order_item_status_changed',
            'label' => 'Staff: item ready',
            'default' => '{item_name} is ready for Table {table_label}.',
            'placeholders' => ['item_name', 'table_label'],
        ],
        'staff.order_cancelled' => [
            'event' => 'order_cancelled',
            'label' => 'Staff: order cancelled',
            'default' => 'Order #{order_number} for Table {table_label} was cancelled.',
            'placeholders' => ['order_number', 'table_label'],
        ],
        'staff.payment_updated' => [
            'event' => 'payment_updated',
            'label' => 'Staff: payment updated',
            'default' => 'Payment updated for Order #{order_number}.',
            'placeholders' => ['order_number'],
        ],
        'staff.table_session_changed' => [
            'event' => 'table_session_changed',
            'label' => 'Staff: table session changed',
            'default' => 'Table {table_label} session was updated.',
            'placeholders' => ['table_label'],
        ],
    ];

    public function __construct(
        private readonly LocaleService $locales,
    ) {}

    public static function definitions(): array
    {
        return self::TEMPLATES;
    }

    public function render(Notification $notification, string $locale): string
    {
        $metadata = is_array($notification->metadata) ? $notification->metadata : [];
        $key = (string) ($metadata['template'] ?? $notification->event);
        $template = $this->messageFor($key, $locale)
            ?? $this->messageFor($key, 'en')
            ?? (self::TEMPLATES[$key]['default'] ?? null)
            ?? $notification->message;

        return $this->replacePlaceholders($template, $this->placeholderValues($notification, $locale));
    }

    public function messageFor(string $key, string $locale): ?string
    {
        $language = $this->locales->normalize($locale) ?? 'en';

        return NotificationTemplate::where('key', $key)
            ->where('language', $language)
            ->value('message');
    }

    private function replacePlaceholders(string $template, array $values): string
    {
        foreach ($values as $key => $value) {
            $template = str_replace('{'.$key.'}', (string) $value, $template);
        }

        return $template;
    }

    private function placeholderValues(Notification $notification, string $locale): array
    {
        $metadata = is_array($notification->metadata) ? $notification->metadata : [];

        return [
            'customer_name' => $metadata['customer_name'] ?? $this->customerName($metadata['customer_id'] ?? null),
            'item_name' => $this->itemName($metadata, $notification->vendor, $locale),
            'table_label' => $metadata['table_label'] ?? $this->tableLabel($metadata['table_id'] ?? null),
            'order_number' => $metadata['order_number'] ?? $metadata['order_id'] ?? '',
        ];
    }

    private function customerName(mixed $customerId): string
    {
        if (! $customerId) {
            return 'A guest';
        }

        $customer = Customer::find($customerId, ['id', 'first_name', 'last_name']);

        return $customer
            ? (trim(($customer->first_name ?? '').' '.($customer->last_name ?? '')) ?: 'A guest')
            : 'A guest';
    }

    private function itemName(array $metadata, ?Vendor $vendor, string $locale): string
    {
        $itemId = $metadata['menu_item_id'] ?? null;
        if (! $itemId) {
            return (string) ($metadata['item_name'] ?? 'an item');
        }

        $item = MenuItem::with(['itemTranslations', 'vendor.vendorSetting'])->find($itemId);
        if (! $item) {
            return (string) ($metadata['item_name'] ?? 'an item');
        }

        $itemVendor = $vendor ?? $item->vendor;

        return (string) $this->locales->translated(
            $item,
            'itemTranslations',
            'name',
            $itemVendor,
            $locale,
            $item->name,
        );
    }

    private function tableLabel(mixed $tableId): string
    {
        if (! $tableId) {
            return '';
        }

        $table = RestaurantTable::find($tableId, ['id', 'number', 'name']);

        return $table ? ($table->name ?? '#'.$table->number) : '';
    }
}
