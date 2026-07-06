<?php

namespace Tests\Feature\Customer;

use App\Models\CartItem;
use App\Models\Customer;
use App\Models\CustomerLoyaltyPoint;
use App\Models\GdprRequest;
use App\Models\LoyaltyTransaction;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\RestaurantTable;
use App\Models\Review;
use App\Models\ReviewItem;
use App\Models\TableScanSession;
use App\Models\Vendor;
use App\Models\VendorSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CustomerFeaturesTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    private Vendor $vendor;

    private array $headers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = Customer::factory()->create();
        $this->vendor = Vendor::factory()->create();
        VendorSetting::create([
            'vendor_id' => $this->vendor->id,
            'is_live_and_discoverable' => true,
            'enable_reservations' => true,
        ]);

        $token = $this->customer->createToken('test', ['role:customer'])->plainTextToken;
        $this->headers = [
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/json',
        ];
    }

    private function tableScanSession(?Customer $customer = null): TableScanSession
    {
        $table = RestaurantTable::create([
            'vendor_id' => $this->vendor->id,
            'number' => 1,
            'name' => 'Table 1',
            'qr_token' => RestaurantTable::generateQrToken(),
            'is_active' => true,
            'qr_created_at' => now(),
        ]);

        return TableScanSession::create([
            'vendor_id' => $this->vendor->id,
            'restaurant_table_id' => $table->id,
            'customer_id' => ($customer ?? $this->customer)->id,
            'pin' => '1234',
            'status' => 'active',
            'scanned_at' => now(),
        ]);
    }

    // ================================================================
    // ORDER HISTORY
    // ================================================================

    public function test_can_get_order_history_restaurants(): void
    {
        $session = $this->tableScanSession();

        Order::factory()->create([
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'table_scan_session_id' => $session->id,
            'amount' => 25.50,
        ]);

        $response = $this->getJson('/api/customer/orders/restaurants', $this->headers);

        $response->assertOk()
            ->assertJsonCount(1);
    }

    public function test_can_get_account_order_history_grouped_by_restaurant_with_paginated_orders(): void
    {
        $this->vendor->update([
            'vendor_public_id' => 'REST-101',
            'restaurant_name' => 'Bella Italia',
        ]);
        $this->vendor->vendorSetting()->update([
            'logo_url' => 'vendors/1/logo.png',
            'date_format' => 'MM/DD/YYYY',
            'time_format' => '12h',
        ]);

        $category = MenuCategory::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Mains',
            'slug' => 'mains',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $menuItem = MenuItem::create([
            'vendor_id' => $this->vendor->id,
            'menu_category_id' => $category->id,
            'name' => 'Tonkotsu Ramen',
            'price' => 16.24,
            'image_url' => 'menu-items/42/photo.png',
            'is_active' => true,
            'available' => true,
        ]);
        $legacyMenuItem = MenuItem::create([
            'vendor_id' => $this->vendor->id,
            'menu_category_id' => $category->id,
            'name' => 'Matcha Latte',
            'price' => 5.00,
            'is_active' => true,
            'available' => true,
        ]);

        $session = $this->tableScanSession();
        $cartItem = CartItem::create([
            'table_scan_session_id' => $session->id,
            'menu_item_id' => $menuItem->id,
            'quantity' => 1,
            'notes' => null,
        ]);

        $first = Order::factory()->create([
            'order_public_id' => 'ord-aB3xK9pQrS12',
            'order_number' => 'ORD-8801',
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'table_scan_session_id' => $session->id,
            'order_type' => 'dine-in',
            'payment_received' => true,
            'payment_pending' => false,
            'payment_method' => 'card',
            'service_fee' => 0,
            'vat_amount' => 4,
            'amount' => 24.24,
            'currency' => 'USD',
            'created_at' => now()->subDay(),
        ]);
        $cartItem->update(['order_id' => $first->id]);

        CartItem::create([
            'table_scan_session_id' => $session->id,
            'menu_item_id' => $legacyMenuItem->id,
            'quantity' => 2,
            'notes' => null,
        ]);

        $second = Order::factory()->create([
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'table_scan_session_id' => $session->id,
            'payment_received' => true,
            'payment_pending' => false,
            'amount' => 10,
            'currency' => 'USD',
            'created_at' => now(),
        ]);

        $this->getJson('/api/customer/orders/history?per_page=1&page=1', $this->headers)
            ->assertOk()
            ->assertJsonPath('history.0.orders.0.order_public_id', $second->order_public_id)
            ->assertJsonPath('history.0.orders.0.items_count', 2)
            ->assertJsonPath('history.0.orders.0.items.0.name', 'Matcha Latte')
            ->assertJsonPath('history.0.orders.0.items.0.quantity', 2);

        $response = $this->getJson('/api/customer/orders/history?per_page=1&page=2', $this->headers);

        $response->assertOk()
            ->assertJsonPath('history.0.restaurant_public_id', 'REST-101')
            ->assertJsonPath('history.0.restaurant_name', 'Bella Italia')
            ->assertJsonPath('history.0.currency', 'USD')
            ->assertJsonPath('history.0.orders_count', 2)
            ->assertJsonPath('history.0.total_spent', 34.24)
            ->assertJsonPath('history.0.last_ordered_at', $second->created_at->copy()->setTimezone($this->vendor->resolveTimezone())->format('m/d/Y g:i A'))
            ->assertJsonPath('history.0.orders.0.order_id', 'ORD-8801')
            ->assertJsonPath('history.0.orders.0.order_public_id', $first->order_public_id)
            ->assertJsonPath('history.0.orders.0.created_at', $first->created_at->copy()->setTimezone($this->vendor->resolveTimezone())->format('m/d/Y g:i A'))
            ->assertJsonPath('history.0.orders.0.payment_status', 'paid')
            ->assertJsonPath('history.0.orders.0.payment_method', 'card')
            ->assertJsonPath('history.0.orders.0.items_count', 1)
            ->assertJsonPath('history.0.orders.0.total_amount', 24.24)
            ->assertJsonPath('history.0.orders.0.items.0.name', 'Tonkotsu Ramen')
            ->assertJsonPath('history.0.orders.0.items.0.quantity', 1)
            ->assertJsonPath('history.0.orders.0.items.0.unit_price', 17.86)
            ->assertJsonPath('history.0.pagination.current_page', 2)
            ->assertJsonPath('history.0.pagination.per_page', 1)
            ->assertJsonPath('history.0.pagination.total', 2)
            ->assertJsonPath('history.0.pagination.last_page', 2)
            ->assertJsonPath('history.0.pagination.has_more', false)
            ->assertJsonPath('summary.restaurants_count', 1)
            ->assertJsonPath('summary.orders_count', 2)
            ->assertJsonPath('summary.total_spent', 34.24);
    }

    public function test_can_get_vendor_orders(): void
    {
        $this->vendor->vendorSetting()->update([
            'date_format' => 'MM/DD/YYYY',
            'time_format' => '12h',
        ]);
        $session = $this->tableScanSession();

        Order::factory()->count(3)->create([
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'table_scan_session_id' => $session->id,
        ]);

        $response = $this->getJson(
            "/api/customer/orders/restaurants/{$this->vendor->vendor_public_id}",
            $this->headers
        );

        $response->assertOk()
            ->assertJsonPath('summary.total_orders', 3);
        $this->assertMatchesRegularExpression(
            '/^\d{2}\/\d{2}\/\d{4} \d{1,2}:\d{2} [AP]M$/',
            $response->json('orders.data.0.created_at')
        );
    }

    public function test_can_get_single_order(): void
    {
        $this->vendor->update([
            'vendor_public_id' => 'REST-101',
            'restaurant_name' => 'Bella Italia',
        ]);
        $this->vendor->vendorSetting()->update([
            'logo_url' => 'vendors/1/logo.png',
            'date_format' => 'MM/DD/YYYY',
            'time_format' => '12h',
        ]);

        $category = MenuCategory::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Drinks',
            'slug' => 'drinks',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $ramen = MenuItem::create([
            'vendor_id' => $this->vendor->id,
            'menu_category_id' => $category->id,
            'name' => 'Tonkotsu Ramen',
            'price' => 16.24,
            'is_active' => true,
            'available' => true,
        ]);
        $latte = MenuItem::create([
            'vendor_id' => $this->vendor->id,
            'menu_category_id' => $category->id,
            'name' => 'Matcha Latte',
            'price' => 8.00,
            'is_active' => true,
            'available' => true,
        ]);

        $session = $this->tableScanSession();
        $ramenCartItem = CartItem::create([
            'table_scan_session_id' => $session->id,
            'menu_item_id' => $ramen->id,
            'quantity' => 1,
            'notes' => null,
        ]);
        $latteCartItem = CartItem::create([
            'table_scan_session_id' => $session->id,
            'menu_item_id' => $latte->id,
            'quantity' => 1,
            'notes' => null,
        ]);

        $order = Order::factory()->create([
            'order_number' => 'ORD-8842',
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'table_scan_session_id' => $session->id,
            'status' => 'delivered',
            'order_type' => 'dine-in',
            'payment_received' => true,
            'payment_pending' => false,
            'payment_method' => 'card',
            'service_fee' => 0,
            'vat_amount' => 4,
            'amount' => 24.24,
            'currency' => 'USD',
        ]);
        CartItem::whereIn('id', [$ramenCartItem->id, $latteCartItem->id])
            ->update(['order_id' => $order->id]);

        $response = $this->getJson(
            "/api/customer/orders/{$order->order_public_id}",
            $this->headers
        );

        $response->assertOk()
            ->assertJsonPath('order_id', 'ORD-8842')
            ->assertJsonPath('order_public_id', $order->order_public_id)
            ->assertJsonPath('restaurant.restaurant_public_id', 'REST-101')
            ->assertJsonPath('restaurant.restaurant_name', 'Bella Italia')
            ->assertJsonPath('created_at', $order->created_at->copy()->setTimezone($this->vendor->resolveTimezone())->format('m/d/Y g:i A'))
            ->assertJsonPath('status', 'delivered')
            ->assertJsonPath('order_type', 'dine-in')
            ->assertJsonPath('payment_status', 'paid')
            ->assertJsonPath('payment_method', 'card')
            ->assertJsonPath('items.0.menu_item_id', $ramen->id)
            ->assertJsonPath('items.0.name', 'Tonkotsu Ramen')
            ->assertJsonPath('items.0.quantity', 1)
            ->assertJsonPath('items.0.unit_price', 17.86)
            ->assertJsonPath('items.0.line_total', 17.86)
            ->assertJsonPath('items.1.menu_item_id', $latte->id)
            ->assertJsonPath('items.1.name', 'Matcha Latte')
            ->assertJsonPath('items.1.unit_price', 8.8)
            ->assertJsonPath('totals.net_total', 24.24)
            ->assertJsonPath('totals.vat_total', 2.42)
            ->assertJsonPath('totals.service_fee', 0)
            ->assertJsonPath('totals.grand_total', 26.66)
            ->assertJsonPath('totals.currency', 'USD');
    }

    public function test_cannot_view_other_customers_order(): void
    {
        $other = Customer::factory()->create();
        $session = $this->tableScanSession($other);
        $order = Order::factory()->create([
            'customer_id' => $other->id,
            'vendor_id' => $this->vendor->id,
            'table_scan_session_id' => $session->id,
        ]);

        $this->getJson(
            "/api/customer/orders/{$order->order_public_id}",
            $this->headers
        )->assertNotFound();
    }

    public function test_can_get_order_receipt(): void
    {
        $this->vendor->update([
            'restaurant_name' => 'La Bella Cucina',
            'vat_number' => 'ATU12345678',
            'business_registration_number' => 'FN 123456 a',
            'address' => 'Mariahilfer Straße 45',
            'city' => 'Vienna',
            'country' => 'AT',
            'phone' => '+43 1 234 5678',
            'email' => 'info@labellacucina.at',
        ]);
        $this->vendor->vendorSetting()->update([
            'service_fee_rate' => 10,
            'invoice_prefix' => 'INV',
            'next_invoice_number' => 1001,
            'date_format' => 'MM/DD/YYYY',
            'time_format' => '12h',
        ]);

        $category = MenuCategory::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Mains',
            'slug' => 'mains',
            'is_active' => true,
        ]);

        $food = MenuItem::create([
            'vendor_id' => $this->vendor->id,
            'menu_category_id' => $category->id,
            'name' => 'Bruschetta al Pomodoro',
            'price' => 8.00,
            'is_active' => true,
            'available' => true,
        ]);

        $session = $this->tableScanSession();
        $order = Order::factory()->create([
            'order_public_id' => 'ord-receipt-test',
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'table_scan_session_id' => $session->id,
            'status' => 'confirmed',
            'amount' => 9.68,
            'service_fee' => 0.88,
            'currency' => 'EUR',
            'payment_received' => true,
            'payment_confirmed_at' => now(),
            'payment_method' => 'stripe',
            'transaction_id' => 'pi_test123',
        ]);

        CartItem::create([
            'table_scan_session_id' => $session->id,
            'menu_item_id' => $food->id,
            'order_id' => $order->id,
            'quantity' => 1,
        ]);

        $response = $this->getJson(
            "/api/customer/orders/{$order->order_public_id}/receipt",
            $this->headers
        );

        $response->assertOk()
            ->assertJsonPath('data.restaurant.name', 'La Bella Cucina')
            ->assertJsonPath('data.restaurant.vat_id', 'ATU12345678')
            ->assertJsonPath('data.restaurant.phone', '+43 1 234 5678')
            ->assertJsonPath('data.restaurant.company_register_number', 'FN 123456 a')
            ->assertJsonPath('data.receipt.invoice_number', 'INV-0001001')
            ->assertJsonPath('data.receipt.date', $order->created_at->copy()->setTimezone($this->vendor->resolveTimezone())->format('m/d/Y'))
            ->assertJsonPath('data.receipt.time', $order->created_at->copy()->setTimezone($this->vendor->resolveTimezone())->format('g:i A'))
            ->assertJsonPath('data.receipt.order_id', 'ord-receipt-test')
            ->assertJsonPath('data.receipt.currency', 'EUR')
            ->assertJsonPath('data.order.items.0.name', 'Bruschetta al Pomodoro')
            ->assertJsonPath('data.order.items.0.quantity', 1)
            ->assertJsonPath('data.order.items.0.unit_price_gross', 8.80)
            ->assertJsonPath('data.order.items.0.line_gross', 8.80)
            ->assertJsonPath('data.order.items.0.tax_category', 'FOOD')
            ->assertJsonPath('data.order.items.0.vat_rate', 10)
            ->assertJsonPath('data.tax_groups.0.code', 'A')
            ->assertJsonPath('data.tax_groups.0.tax_category', 'FOOD')
            ->assertJsonPath('data.totals.net_total', 8)
            ->assertJsonPath('data.totals.vat_total', 0.8)
            ->assertJsonPath('data.totals.service_fee', 0.88)
            ->assertJsonPath('data.totals.grand_total', 9.68)
            ->assertJsonPath('data.payment.method', 'stripe')
            ->assertJsonPath('data.payment.status', 'CONFIRMED')
            ->assertJsonPath('data.payment.transaction_id', 'pi_test123')
            ->assertJsonPath('data.payment.paid_at', $order->payment_confirmed_at->copy()->setTimezone($this->vendor->resolveTimezone())->format('m/d/Y g:i A'))
            ->assertJsonPath('data.legal.rksv_required_check', true)
            ->assertJsonPath('meta.version', '1.0');
        $this->assertMatchesRegularExpression(
            '/^\d{2}\/\d{2}\/\d{4} \d{1,2}:\d{2} [AP]M$/',
            $response->json('meta.generated_at')
        );

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'invoice_number' => 'INV-0001001',
        ]);

        $this->assertDatabaseHas('vendor_settings', [
            'vendor_id' => $this->vendor->id,
            'next_invoice_number' => 1002,
        ]);
    }

    public function test_receipt_requires_paid_order(): void
    {
        $session = $this->tableScanSession();
        $order = Order::factory()->create([
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'table_scan_session_id' => $session->id,
            'payment_received' => false,
        ]);

        $this->getJson(
            "/api/customer/orders/{$order->order_public_id}/receipt",
            $this->headers
        )->assertStatus(422)
            ->assertJsonPath('message', 'Receipt is only available for paid orders.');
    }

    public function test_receipt_reuses_existing_invoice_number(): void
    {
        $this->vendor->update(['country' => 'AT']);
        $this->vendor->vendorSetting()->update([
            'invoice_prefix' => 'INV',
            'next_invoice_number' => 2000,
        ]);

        $session = $this->tableScanSession();
        $order = Order::factory()->create([
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'table_scan_session_id' => $session->id,
            'payment_received' => true,
            'payment_confirmed_at' => now(),
            'invoice_number' => 'INV-EXISTING',
        ]);

        $response = $this->getJson(
            "/api/customer/orders/{$order->order_public_id}/receipt",
            $this->headers
        );

        $response->assertOk()
            ->assertJsonPath('data.receipt.invoice_number', 'INV-EXISTING');

        $this->assertDatabaseHas('vendor_settings', [
            'vendor_id' => $this->vendor->id,
            'next_invoice_number' => 2000,
        ]);
    }

    // ================================================================
    // RESERVATIONS
    // ================================================================

    public function test_can_create_reservation(): void
    {
        $response = $this->postJson('/api/customer/reservations', [
            'vendor_public_id' => $this->vendor->vendor_public_id,
            'date' => now()->addDays(3)->format('Y-m-d'),
            'time' => '19:00',
            'party_size' => 4,
            'customer_note' => 'Window seat please',
        ], $this->headers);

        $response->assertCreated()
            ->assertJsonPath('reservation.status', 'pending');

        $this->assertDatabaseHas('reservations', [
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'party_size' => 4,
        ]);
    }

    public function test_cannot_reserve_in_past(): void
    {
        $response = $this->postJson('/api/customer/reservations', [
            'vendor_public_id' => $this->vendor->vendor_public_id,
            'date' => now()->subDay()->format('Y-m-d'),
            'time' => '19:00',
            'party_size' => 2,
        ], $this->headers);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['date']);
    }

    public function test_can_list_upcoming_reservations(): void
    {
        $this->vendor->vendorSetting()->update([
            'date_format' => 'MM/DD/YYYY',
            'time_format' => '12h',
        ]);
        $reservation = Reservation::create([
            'reservation_public_id' => 'res_test1',
            'vendor_id' => $this->vendor->id,
            'customer_id' => $this->customer->id,
            'guest_name' => $this->customer->name,
            'guest_email' => $this->customer->email,
            'guest_phone' => $this->customer->phone,
            'date' => now()->addDays(5)->format('Y-m-d'),
            'time' => '20:00',
            'party_size' => 2,
            'status' => 'confirmed',
        ]);

        $response = $this->getJson('/api/customer/reservations?tab=upcoming', $this->headers);

        $response->assertOk()
            ->assertJsonPath('data.0.status', 'confirmed')
            ->assertJsonPath('data.0.date', $reservation->date->format('m/d/Y'))
            ->assertJsonPath('data.0.time', '8:00 PM')
            ->assertJsonPath('data.0.created_at', $reservation->created_at->format('m/d/Y g:i A'));
    }

    public function test_can_cancel_reservation(): void
    {
        $reservation = Reservation::create([
            'reservation_public_id' => 'res_cancel1',
            'vendor_id' => $this->vendor->id,
            'customer_id' => $this->customer->id,
            'guest_name' => $this->customer->name,
            'guest_email' => $this->customer->email,
            'guest_phone' => $this->customer->phone,
            'date' => now()->addDays(5)->format('Y-m-d'),
            'time' => '20:00',
            'party_size' => 2,
            'status' => 'pending',
        ]);

        $response = $this->postJson(
            "/api/customer/reservations/{$reservation->reservation_public_id}/cancel",
            [],
            $this->headers
        );

        $response->assertOk()
            ->assertJsonPath('reservation.status', 'cancelled');
    }

    // ================================================================
    // FAVORITES
    // ================================================================

    public function test_can_add_favorite(): void
    {
        $response = $this->postJson(
            "/api/customer/favorites/{$this->vendor->vendor_public_id}/add",
            [],
            $this->headers
        );

        $response->assertCreated();

        $this->assertTrue(
            $this->customer->favorites()->where('vendor_id', $this->vendor->id)->exists()
        );
    }

    public function test_can_list_favorites(): void
    {
        $this->customer->favorites()->attach($this->vendor->id);
        $vendorDay = strtolower(now()->setTimezone($this->vendor->resolveTimezone())->format('l'));
        $this->vendor->vendorSetting()->update([
            'time_format' => '12h',
            'business_hours' => [
                $vendorDay => [
                    'open' => '00:00',
                    'close' => '23:59',
                    'closed' => false,
                ],
            ],
        ]);
        MenuCategory::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Burgers',
            'slug' => 'burgers',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $response = $this->getJson('/api/customer/favorites', $this->headers);

        $response->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.business_hours.'.$vendorDay.'.closed', false)
            ->assertJsonPath('0.business_hours.'.$vendorDay.'.open', '12:00 AM')
            ->assertJsonPath('0.business_hours.'.$vendorDay.'.close', '11:59 PM')
            ->assertJsonPath('0.cuisines.0', 'Burgers');
    }

    public function test_can_remove_favorite(): void
    {
        $this->customer->favorites()->attach($this->vendor->id);

        $response = $this->deleteJson(
            "/api/customer/favorites/{$this->vendor->vendor_public_id}/delete",
            [],
            $this->headers
        );

        $response->assertOk();
        $this->assertFalse(
            $this->customer->favorites()->where('vendor_id', $this->vendor->id)->exists()
        );
    }

    // ================================================================
    // REVIEWS
    // ================================================================

    public function test_customer_with_served_dine_in_order_can_leave_review(): void
    {
        $session = $this->tableScanSession();
        $category = MenuCategory::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Mains',
            'slug' => 'mains-review',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $menuItem = MenuItem::create([
            'vendor_id' => $this->vendor->id,
            'menu_category_id' => $category->id,
            'name' => 'Margherita Pizza',
            'price' => 12.00,
            'is_active' => true,
            'available' => true,
        ]);

        $order = Order::factory()->create([
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'table_scan_session_id' => $session->id,
            'payment_received' => true,
            'payment_pending' => false,
            'status' => 'served',
        ]);

        $cartItem = CartItem::create([
            'table_scan_session_id' => $session->id,
            'menu_item_id' => $menuItem->id,
            'order_id' => $order->id,
            'quantity' => 1,
            'served_at' => now(),
        ]);

        $response = $this->postJson('/api/customer/reviews', [
            'session_scan_table_id' => $session->id,
            'rating' => 5,
            'review' => 'Amazing food!',
            'items' => [
                [
                    'cart_item_id' => $cartItem->id,
                    'rating' => 5,
                    'review' => 'Best pizza ever',
                ],
            ],
        ], $this->headers);

        $response->assertCreated()
            ->assertJsonPath('review.session_scan_table_id', $session->id)
            ->assertJsonPath('review.rating', 5)
            ->assertJsonPath('review.text', 'Amazing food!')
            ->assertJsonPath('review.items.0.rating', 5)
            ->assertJsonPath('review.items.0.text', 'Best pizza ever')
            ->assertJsonPath('review.items.0.menu_item_name', 'Margherita Pizza');

        $menuItem->refresh();
        $this->assertEquals(5.00, (float) $menuItem->rating);
        $this->assertEquals(1, $menuItem->review_count);
    }

    public function test_can_get_reviewable_orders_for_a_session(): void
    {
        $session = $this->tableScanSession();
        $category = MenuCategory::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Reviewable',
            'slug' => 'reviewable-orders',
            'is_active' => true,
        ]);
        $menuItem = MenuItem::create([
            'vendor_id' => $this->vendor->id,
            'menu_category_id' => $category->id,
            'name' => 'Reviewable Item',
            'price' => 10,
            'is_active' => true,
            'available' => true,
        ]);

        $firstOrder = Order::factory()->create([
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'table_scan_session_id' => $session->id,
            'amount' => 20,
            'tip_amount' => 3,
            'payment_received' => true,
        ]);
        $secondOrder = Order::factory()->create([
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'table_scan_session_id' => $session->id,
            'amount' => 8.50,
            'tip_amount' => 0,
            'payment_received' => true,
        ]);

        $firstItem = CartItem::create([
            'table_scan_session_id' => $session->id,
            'menu_item_id' => $menuItem->id,
            'order_id' => $firstOrder->id,
            'quantity' => 1,
            'served_at' => now(),
        ]);
        $secondItem = CartItem::create([
            'table_scan_session_id' => $session->id,
            'menu_item_id' => $menuItem->id,
            'order_id' => $secondOrder->id,
            'quantity' => 1,
            'served_at' => now(),
        ]);

        $this->getJson("/api/customer/reviews/session/{$session->id}", $this->headers)
            ->assertOk()
            ->assertJsonPath('session_scan_table_id', $session->id)
            ->assertJsonPath('orders.0.order_id', $firstOrder->order_public_id)
            ->assertJsonPath('orders.0.total_amount_paid', 23)
            ->assertJsonPath('orders.0.items.0.cart_item_id', $firstItem->id)
            ->assertJsonPath('orders.1.order_id', $secondOrder->order_public_id)
            ->assertJsonPath('orders.1.total_amount_paid', 8.5)
            ->assertJsonPath('orders.1.items.0.cart_item_id', $secondItem->id);
    }

    public function test_reviewable_session_endpoint_requires_paid_orders_and_served_items(): void
    {
        $session = $this->tableScanSession();

        $this->getJson("/api/customer/reviews/session/{$session->id}", $this->headers)
            ->assertUnprocessable()
            ->assertJsonPath('errors.session_scan_table_id.0', 'This session has no orders.');

        $category = MenuCategory::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Pending Review',
            'slug' => 'pending-review',
            'is_active' => true,
        ]);
        $menuItem = MenuItem::create([
            'vendor_id' => $this->vendor->id,
            'menu_category_id' => $category->id,
            'name' => 'Pending Item',
            'price' => 5,
            'is_active' => true,
            'available' => true,
        ]);
        $order = Order::factory()->create([
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'table_scan_session_id' => $session->id,
            'payment_received' => false,
        ]);
        $cartItem = CartItem::create([
            'table_scan_session_id' => $session->id,
            'menu_item_id' => $menuItem->id,
            'order_id' => $order->id,
            'quantity' => 1,
            'served_at' => null,
        ]);

        $this->getJson("/api/customer/reviews/session/{$session->id}", $this->headers)
            ->assertOk()
            ->assertJsonPath('all_paid', false)
            ->assertJsonPath('all_served', false)
            ->assertJsonPath('reviewable', false)
            ->assertJsonPath('reviewed', false);

        $order->update(['payment_received' => true]);

        $this->getJson("/api/customer/reviews/session/{$session->id}", $this->headers)
            ->assertOk()
            ->assertJsonPath('all_paid', true)
            ->assertJsonPath('all_served', false)
            ->assertJsonPath('reviewable', false);

        $cartItem->update(['served_at' => now()]);
        $order->update(['status' => 'served', 'served_at' => now()]);

        $this->getJson("/api/customer/reviews/session/{$session->id}", $this->headers)
            ->assertOk()
            ->assertJsonPath('all_paid', true)
            ->assertJsonPath('all_served', true)
            ->assertJsonPath('reviewable', true)
            ->assertJsonPath('reviewed', false);

        Review::create([
            'review_public_id' => 'rev_session_endpoint',
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'table_scan_session_id' => $session->id,
            'rating' => 5,
        ]);

        $this->getJson("/api/customer/reviews/session/{$session->id}", $this->headers)
            ->assertOk()
            ->assertJsonPath('reviewed', true)
            ->assertJsonPath('reviewable', false)
            ->assertJsonPath('review.rating', 5)
            ->assertJsonPath('review.review_public_id', 'rev_session_endpoint');
    }

    public function test_session_review_accepts_overall_and_item_photos(): void
    {
        Storage::fake('public');

        $session = $this->tableScanSession();
        $category = MenuCategory::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Photo Review',
            'slug' => 'photo-review',
            'is_active' => true,
        ]);
        $menuItem = MenuItem::create([
            'vendor_id' => $this->vendor->id,
            'menu_category_id' => $category->id,
            'name' => 'Photogenic Pizza',
            'price' => 15,
            'is_active' => true,
            'available' => true,
        ]);
        $order = Order::factory()->create([
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'table_scan_session_id' => $session->id,
            'payment_received' => true,
        ]);
        $cartItem = CartItem::create([
            'table_scan_session_id' => $session->id,
            'menu_item_id' => $menuItem->id,
            'order_id' => $order->id,
            'quantity' => 1,
            'served_at' => now(),
        ]);

        $response = $this->post('/api/customer/reviews', [
            'session_scan_table_id' => $session->id,
            'rating' => 5,
            'review' => 'Looks as good as it tastes.',
            'photos' => [UploadedFile::fake()->image('table.jpg')],
            'items' => [[
                'cart_item_id' => $cartItem->id,
                'rating' => 5,
                'review' => 'Perfect crust.',
                'photos' => [UploadedFile::fake()->image('pizza.png')],
            ]],
        ], $this->headers);

        $response->assertCreated()
            ->assertJsonCount(1, 'review.photos')
            ->assertJsonCount(1, 'review.items.0.photos');

        $review = Review::with('items')->where('table_scan_session_id', $session->id)->firstOrFail();
        $reviewItem = ReviewItem::where('review_id', $review->id)->firstOrFail();

        Storage::disk('public')->assertExists($review->images[0]);
        Storage::disk('public')->assertExists($reviewItem->images[0]);
    }

    public function test_customer_without_any_qualifying_order_cannot_review(): void
    {
        $response = $this->postJson('/api/customer/reviews', [
            'session_scan_table_id' => 999999,
            'rating' => 5,
            'review' => 'Amazing food!',
            'items' => [['cart_item_id' => 1, 'rating' => 5]],
        ], $this->headers);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['session_scan_table_id']);
    }

    public function test_cannot_review_unpaid_order(): void
    {
        $session = $this->tableScanSession();
        $category = MenuCategory::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Sides',
            'slug' => 'sides-review',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $menuItem = MenuItem::create([
            'vendor_id' => $this->vendor->id,
            'menu_category_id' => $category->id,
            'name' => 'Fries',
            'price' => 5.00,
            'is_active' => true,
            'available' => true,
        ]);

        $order = Order::factory()->create([
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'table_scan_session_id' => $session->id,
            'payment_received' => false,
            'payment_pending' => true,
            'status' => 'confirmed',
        ]);

        $cartItem = CartItem::create([
            'table_scan_session_id' => $session->id,
            'menu_item_id' => $menuItem->id,
            'order_id' => $order->id,
            'quantity' => 1,
            'served_at' => now(),
        ]);

        $this->postJson('/api/customer/reviews', [
            'session_scan_table_id' => $session->id,
            'rating' => 5,
            'review' => 'Not yet paid.',
            'items' => [['cart_item_id' => $cartItem->id, 'rating' => 5]],
        ], $this->headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['session_scan_table_id']);
    }

    public function test_cannot_review_order_with_unserved_items(): void
    {
        $session = $this->tableScanSession();
        $category = MenuCategory::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Drinks',
            'slug' => 'drinks-review',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $menuItem = MenuItem::create([
            'vendor_id' => $this->vendor->id,
            'menu_category_id' => $category->id,
            'name' => 'Cola',
            'price' => 3.00,
            'is_active' => true,
            'available' => true,
        ]);

        $order = Order::factory()->create([
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'table_scan_session_id' => $session->id,
            'payment_received' => true,
            'payment_pending' => false,
            'status' => 'confirmed',
        ]);

        $cartItem = CartItem::create([
            'table_scan_session_id' => $session->id,
            'menu_item_id' => $menuItem->id,
            'order_id' => $order->id,
            'quantity' => 1,
            'served_at' => null,
        ]);

        $this->postJson('/api/customer/reviews', [
            'session_scan_table_id' => $session->id,
            'rating' => 4,
            'review' => 'Still waiting...',
            'items' => [['cart_item_id' => $cartItem->id, 'rating' => 4]],
        ], $this->headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['session_scan_table_id'])
            ->assertJsonPath('errors.session_scan_table_id.0', 'Items are not served yet.');
    }

    public function test_cannot_review_same_order_twice(): void
    {
        $session = $this->tableScanSession();
        $category = MenuCategory::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Salads',
            'slug' => 'salads-review',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $menuItem = MenuItem::create([
            'vendor_id' => $this->vendor->id,
            'menu_category_id' => $category->id,
            'name' => 'Caesar Salad',
            'price' => 9.00,
            'is_active' => true,
            'available' => true,
        ]);

        $order = Order::factory()->create([
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'table_scan_session_id' => $session->id,
            'payment_received' => true,
            'payment_pending' => false,
            'status' => 'served',
        ]);

        CartItem::create([
            'table_scan_session_id' => $session->id,
            'menu_item_id' => $menuItem->id,
            'order_id' => $order->id,
            'quantity' => 1,
            'served_at' => now(),
        ]);

        Review::create([
            'review_public_id' => 'rev_existing',
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'order_id' => $order->id,
            'table_scan_session_id' => $session->id,
            'rating' => 4,
        ]);

        $response = $this->postJson('/api/customer/reviews', [
            'session_scan_table_id' => $session->id,
            'rating' => 5,
            'review' => 'Another review',
            'items' => [['cart_item_id' => 1, 'rating' => 5]],
        ], $this->headers);

        $response->assertStatus(422);
    }

    public function test_can_update_review(): void
    {
        $order = Order::factory()->create([
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
        ]);

        $review = Review::create([
            'review_public_id' => 'rev_update',
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'order_id' => $order->id,
            'rating' => 3,
            'text' => 'Was okay',
        ]);

        $response = $this->patchJson(
            "/api/customer/reviews/{$review->review_public_id}",
            ['rating' => 4, 'text' => 'Actually pretty good!'],
            $this->headers
        );

        $response->assertOk()
            ->assertJsonPath('review.rating', 4);
    }

    public function test_can_delete_review(): void
    {
        $review = Review::create([
            'review_public_id' => 'rev_delete',
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'order_id' => Order::factory()->create([
                'customer_id' => $this->customer->id,
                'vendor_id' => $this->vendor->id,
            ])->id,
            'rating' => 2,
        ]);

        $response = $this->deleteJson(
            "/api/customer/reviews/{$review->review_public_id}",
            [],
            $this->headers
        );

        $response->assertOk();
        $this->assertDatabaseMissing('reviews', ['id' => $review->id]);
    }

    public function test_can_list_reviews(): void
    {
        $this->vendor->vendorSetting()->update([
            'date_format' => 'MM/DD/YYYY',
            'time_format' => '12h',
        ]);
        $review = Review::create([
            'review_public_id' => 'rev_list1',
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'order_id' => Order::factory()->create([
                'customer_id' => $this->customer->id,
                'vendor_id' => $this->vendor->id,
            ])->id,
            'rating' => 5,
        ]);

        $response = $this->getJson('/api/customer/reviews', $this->headers);

        $response->assertOk()
            ->assertJsonPath('data.0.rating', 5)
            ->assertJsonPath('data.0.created_at', $review->created_at->format('m/d/Y g:i A'));
    }

    public function test_can_list_only_reviews_for_a_specific_menu_item(): void
    {
        $category = MenuCategory::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Reviewed Items',
            'slug' => 'reviewed-items',
            'is_active' => true,
        ]);
        $menuItem = MenuItem::create([
            'vendor_id' => $this->vendor->id,
            'menu_category_id' => $category->id,
            'name' => 'Margherita Pizza',
            'price' => 12,
            'is_active' => true,
            'available' => true,
        ]);
        $otherMenuItem = MenuItem::create([
            'vendor_id' => $this->vendor->id,
            'menu_category_id' => $category->id,
            'name' => 'Tiramisu',
            'price' => 7,
            'is_active' => true,
            'available' => true,
        ]);

        $otherCustomer = Customer::factory()->create([
            'first_name' => 'Other',
            'last_name' => 'Customer',
        ]);
        $flaggedCustomer = Customer::factory()->create();
        $reviewTable = RestaurantTable::create([
            'vendor_id' => $this->vendor->id,
            'number' => 99,
            'name' => 'Review Table',
            'qr_token' => RestaurantTable::generateQrToken(),
            'is_active' => true,
            'qr_created_at' => now(),
        ]);
        $nextPin = 1200;

        $createItemReview = function (
            Customer $customer,
            MenuItem $item,
            int $rating,
            string $itemText,
            bool $flagged = false,
        ) use ($reviewTable, &$nextPin): array {
            $session = TableScanSession::create([
                'vendor_id' => $this->vendor->id,
                'restaurant_table_id' => $reviewTable->id,
                'customer_id' => $customer->id,
                'pin' => (string) $nextPin++,
                'status' => 'active',
                'scanned_at' => now(),
            ]);
            $order = Order::factory()->create([
                'customer_id' => $customer->id,
                'vendor_id' => $this->vendor->id,
                'table_scan_session_id' => $session->id,
            ]);
            $cartItem = CartItem::create([
                'table_scan_session_id' => $session->id,
                'menu_item_id' => $item->id,
                'order_id' => $order->id,
                'quantity' => 1,
            ]);
            $review = Review::create([
                'review_public_id' => 'rev_item_'.uniqid(),
                'customer_id' => $customer->id,
                'vendor_id' => $this->vendor->id,
                'table_scan_session_id' => $session->id,
                'rating' => 1,
                'text' => 'This overall session review must not be returned.',
                'flagged' => $flagged,
            ]);
            $itemReview = ReviewItem::create([
                'review_id' => $review->id,
                'cart_item_id' => $cartItem->id,
                'menu_item_id' => $item->id,
                'rating' => $rating,
                'text' => $itemText,
            ]);

            return [$review, $itemReview];
        };

        [$firstReview] = $createItemReview($this->customer, $menuItem, 5, 'Excellent pizza.');
        $createItemReview($otherCustomer, $menuItem, 3, 'Good crust.');
        $createItemReview($flaggedCustomer, $menuItem, 1, 'Flagged review.', true);

        $otherCartItem = CartItem::create([
            'table_scan_session_id' => $firstReview->table_scan_session_id,
            'menu_item_id' => $otherMenuItem->id,
            'quantity' => 1,
        ]);
        ReviewItem::create([
            'review_id' => $firstReview->id,
            'cart_item_id' => $otherCartItem->id,
            'menu_item_id' => $otherMenuItem->id,
            'rating' => 2,
            'text' => 'This belongs to another item.',
        ]);

        $response = $this->getJson(
            "/api/customer/reviews/item/{$menuItem->id}",
            $this->headers,
        );

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('menu_item.id', $menuItem->id)
            ->assertJsonPath('menu_item.name', 'Margherita Pizza')
            ->assertJsonPath('review_summary.average_rating', 4)
            ->assertJsonPath('review_summary.total_reviews', 2)
            ->assertJsonPath('review_summary.rating_breakdown.0.star', 5)
            ->assertJsonPath('review_summary.rating_breakdown.0.count', 1)
            ->assertJsonFragment(['text' => 'Excellent pizza.'])
            ->assertJsonFragment(['text' => 'Good crust.'])
            ->assertJsonMissing(['text' => 'Flagged review.'])
            ->assertJsonMissing(['text' => 'This belongs to another item.'])
            ->assertJsonMissing(['text' => 'This overall session review must not be returned.']);
    }

    public function test_item_reviews_require_authentication(): void
    {
        $this->getJson('/api/customer/reviews/item/999999')
            ->assertUnauthorized();
    }

    public function test_notifications_and_loyalty_transactions_follow_vendor_formats(): void
    {
        $this->vendor->vendorSetting()->update([
            'date_format' => 'MM/DD/YYYY',
            'time_format' => '12h',
        ]);

        $notification = Notification::create([
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'event' => 'order_updated',
            'message' => 'Your order is ready.',
            'read' => false,
        ]);
        CustomerLoyaltyPoint::create([
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'points_balance' => 20,
            'total_earned' => 20,
            'total_redeemed' => 0,
        ]);
        $transaction = LoyaltyTransaction::create([
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'type' => 'earned',
            'points' => 20,
        ]);

        $this->getJson('/api/customer/notifications', $this->headers)
            ->assertOk()
            ->assertJsonPath(
                'notifications.0.created_at',
                $notification->created_at->copy()->setTimezone($this->vendor->resolveTimezone())->format('m/d/Y g:i A')
            );

        $this->getJson("/api/customer/loyalty/{$this->vendor->vendor_public_id}", $this->headers)
            ->assertOk()
            ->assertJsonPath(
                'transactions.data.0.created_at',
                $transaction->created_at->copy()->setTimezone($this->vendor->resolveTimezone())->format('m/d/Y g:i A')
            );
    }

    // ================================================================
    // PRIVACY & DATA
    // ================================================================

    public function test_can_request_data_export(): void
    {
        $response = $this->postJson('/api/customer/privacy/export', [], $this->headers);

        $response->assertCreated();

        $this->assertDatabaseHas('gdpr_requests', [
            'customer_id' => $this->customer->id,
            'type' => 'data_export',
            'status' => 'pending',
        ]);
    }

    public function test_cannot_duplicate_pending_export_request(): void
    {
        GdprRequest::create([
            'customer_id' => $this->customer->id,
            'type' => 'data_export',
            'status' => 'pending',
        ]);

        $response = $this->postJson('/api/customer/privacy/export', [], $this->headers);
        $response->assertStatus(422);
    }

    public function test_can_request_account_deletion(): void
    {
        $response = $this->postJson('/api/customer/privacy/delete', [
            'password' => 'password',
            'confirmation' => true,
        ], $this->headers);

        $response->assertCreated();

        $this->assertDatabaseHas('gdpr_requests', [
            'customer_id' => $this->customer->id,
            'type' => 'account_deletion',
            'status' => 'pending',
        ]);
    }

    public function test_account_deletion_fails_with_wrong_password(): void
    {
        $response = $this->postJson('/api/customer/privacy/delete', [
            'password' => 'wrong-password',
            'confirmation' => true,
        ], $this->headers);

        $response->assertStatus(422);
    }

    public function test_can_list_gdpr_requests(): void
    {
        GdprRequest::create([
            'customer_id' => $this->customer->id,
            'type' => 'data_export',
            'status' => 'completed',
        ]);

        $response = $this->getJson('/api/customer/privacy/requests', $this->headers);

        $response->assertOk()
            ->assertJsonCount(1);
    }
}
