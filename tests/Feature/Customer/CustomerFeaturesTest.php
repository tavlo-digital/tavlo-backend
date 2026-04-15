<?php

namespace Tests\Feature\Customer;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\Review;
use App\Models\Vendor;
use App\Models\VendorSetting;
use App\Models\GdprRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            'vendor_id'                => $this->vendor->id,
            'is_live_and_discoverable' => true,
            'enable_reservations'      => true,
        ]);

        $token = $this->customer->createToken('test', ['role:customer'])->plainTextToken;
        $this->headers = [
            'Authorization' => "Bearer {$token}",
            'Accept'        => 'application/json',
        ];
    }

    // ================================================================
    // ORDER HISTORY
    // ================================================================

    public function test_can_get_order_history_restaurants(): void
    {
        Order::factory()->create([
            'customer_id' => $this->customer->id,
            'vendor_id'   => $this->vendor->id,
            'amount'      => 25.50,
        ]);

        $response = $this->getJson('/api/customer/orders/restaurants', $this->headers);

        $response->assertOk()
            ->assertJsonCount(1);
    }

    public function test_can_get_vendor_orders(): void
    {
        Order::factory()->count(3)->create([
            'customer_id' => $this->customer->id,
            'vendor_id'   => $this->vendor->id,
        ]);

        $response = $this->getJson(
            "/api/customer/orders/restaurants/{$this->vendor->vendor_public_id}",
            $this->headers
        );

        $response->assertOk()
            ->assertJsonPath('summary.total_orders', 3);
    }

    public function test_can_get_single_order(): void
    {
        $order = Order::factory()->create([
            'customer_id' => $this->customer->id,
            'vendor_id'   => $this->vendor->id,
        ]);

        $response = $this->getJson(
            "/api/customer/orders/{$order->order_public_id}",
            $this->headers
        );

        $response->assertOk()
            ->assertJsonPath('order_public_id', $order->order_public_id);
    }

    public function test_cannot_view_other_customers_order(): void
    {
        $other = Customer::factory()->create();
        $order = Order::factory()->create([
            'customer_id' => $other->id,
            'vendor_id'   => $this->vendor->id,
        ]);

        $this->getJson(
            "/api/customer/orders/{$order->order_public_id}",
            $this->headers
        )->assertNotFound();
    }

    // ================================================================
    // RESERVATIONS
    // ================================================================

    public function test_can_create_reservation(): void
    {
        $response = $this->postJson('/api/customer/reservations', [
            'vendor_public_id' => $this->vendor->vendor_public_id,
            'date'             => now()->addDays(3)->format('Y-m-d'),
            'time'             => '19:00',
            'party_size'       => 4,
            'customer_note'    => 'Window seat please',
        ], $this->headers);

        $response->assertCreated()
            ->assertJsonPath('reservation.status', 'pending');

        $this->assertDatabaseHas('reservations', [
            'customer_id' => $this->customer->id,
            'vendor_id'   => $this->vendor->id,
            'party_size'  => 4,
        ]);
    }

    public function test_cannot_reserve_in_past(): void
    {
        $response = $this->postJson('/api/customer/reservations', [
            'vendor_public_id' => $this->vendor->vendor_public_id,
            'date'             => now()->subDay()->format('Y-m-d'),
            'time'             => '19:00',
            'party_size'       => 2,
        ], $this->headers);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['date']);
    }

    public function test_can_list_upcoming_reservations(): void
    {
        Reservation::create([
            'reservation_public_id' => 'res_test1',
            'vendor_id'             => $this->vendor->id,
            'customer_id'           => $this->customer->id,
            'guest_name'            => $this->customer->name,
            'guest_email'           => $this->customer->email,
            'guest_phone'           => $this->customer->phone,
            'date'                  => now()->addDays(5)->format('Y-m-d'),
            'time'                  => '20:00',
            'party_size'            => 2,
            'status'                => 'confirmed',
        ]);

        $response = $this->getJson('/api/customer/reservations?tab=upcoming', $this->headers);

        $response->assertOk()
            ->assertJsonPath('data.0.status', 'confirmed');
    }

    public function test_can_cancel_reservation(): void
    {
        $reservation = Reservation::create([
            'reservation_public_id' => 'res_cancel1',
            'vendor_id'             => $this->vendor->id,
            'customer_id'           => $this->customer->id,
            'guest_name'            => $this->customer->name,
            'guest_email'           => $this->customer->email,
            'guest_phone'           => $this->customer->phone,
            'date'                  => now()->addDays(5)->format('Y-m-d'),
            'time'                  => '20:00',
            'party_size'            => 2,
            'status'                => 'pending',
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
        $response = $this->postJson('/api/customer/favorites', [
            'vendor_public_id' => $this->vendor->vendor_public_id,
        ], $this->headers);

        $response->assertCreated();

        $this->assertTrue(
            $this->customer->favorites()->where('vendor_id', $this->vendor->id)->exists()
        );
    }

    public function test_can_list_favorites(): void
    {
        $this->customer->favorites()->attach($this->vendor->id);

        $response = $this->getJson('/api/customer/favorites', $this->headers);

        $response->assertOk()
            ->assertJsonCount(1);
    }

    public function test_can_remove_favorite(): void
    {
        $this->customer->favorites()->attach($this->vendor->id);

        $response = $this->deleteJson(
            "/api/customer/favorites/{$this->vendor->vendor_public_id}",
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

    public function test_can_create_review(): void
    {
        $order = Order::factory()->create([
            'customer_id' => $this->customer->id,
            'vendor_id'   => $this->vendor->id,
        ]);

        $response = $this->postJson('/api/customer/reviews', [
            'order_public_id' => $order->order_public_id,
            'rating'          => 5,
            'text'            => 'Amazing food!',
        ], $this->headers);

        $response->assertCreated()
            ->assertJsonPath('review.rating', 5);
    }

    public function test_cannot_review_same_order_twice(): void
    {
        $order = Order::factory()->create([
            'customer_id' => $this->customer->id,
            'vendor_id'   => $this->vendor->id,
        ]);

        Review::create([
            'review_public_id' => 'rev_existing',
            'customer_id'      => $this->customer->id,
            'vendor_id'        => $this->vendor->id,
            'order_id'         => $order->id,
            'rating'           => 4,
        ]);

        $response = $this->postJson('/api/customer/reviews', [
            'order_public_id' => $order->order_public_id,
            'rating'          => 5,
        ], $this->headers);

        $response->assertStatus(422);
    }

    public function test_can_update_review(): void
    {
        $review = Review::create([
            'review_public_id' => 'rev_update',
            'customer_id'      => $this->customer->id,
            'vendor_id'        => $this->vendor->id,
            'order_id'         => Order::factory()->create([
                'customer_id' => $this->customer->id,
                'vendor_id'   => $this->vendor->id,
            ])->id,
            'rating'           => 3,
            'text'             => 'Was okay',
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
            'customer_id'      => $this->customer->id,
            'vendor_id'        => $this->vendor->id,
            'order_id'         => Order::factory()->create([
                'customer_id' => $this->customer->id,
                'vendor_id'   => $this->vendor->id,
            ])->id,
            'rating'           => 2,
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
        Review::create([
            'review_public_id' => 'rev_list1',
            'customer_id'      => $this->customer->id,
            'vendor_id'        => $this->vendor->id,
            'order_id'         => Order::factory()->create([
                'customer_id' => $this->customer->id,
                'vendor_id'   => $this->vendor->id,
            ])->id,
            'rating'           => 5,
        ]);

        $response = $this->getJson('/api/customer/reviews', $this->headers);

        $response->assertOk()
            ->assertJsonPath('data.0.rating', 5);
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
            'type'        => 'data_export',
            'status'      => 'pending',
        ]);
    }

    public function test_cannot_duplicate_pending_export_request(): void
    {
        GdprRequest::create([
            'customer_id' => $this->customer->id,
            'type'        => 'data_export',
            'status'      => 'pending',
        ]);

        $response = $this->postJson('/api/customer/privacy/export', [], $this->headers);
        $response->assertStatus(422);
    }

    public function test_can_request_account_deletion(): void
    {
        $response = $this->postJson('/api/customer/privacy/delete', [
            'password'     => 'password',
            'confirmation' => true,
        ], $this->headers);

        $response->assertCreated();

        $this->assertDatabaseHas('gdpr_requests', [
            'customer_id' => $this->customer->id,
            'type'        => 'account_deletion',
            'status'      => 'pending',
        ]);
    }

    public function test_account_deletion_fails_with_wrong_password(): void
    {
        $response = $this->postJson('/api/customer/privacy/delete', [
            'password'     => 'wrong-password',
            'confirmation' => true,
        ], $this->headers);

        $response->assertStatus(422);
    }

    public function test_can_list_gdpr_requests(): void
    {
        GdprRequest::create([
            'customer_id' => $this->customer->id,
            'type'        => 'data_export',
            'status'      => 'completed',
        ]);

        $response = $this->getJson('/api/customer/privacy/requests', $this->headers);

        $response->assertOk()
            ->assertJsonCount(1);
    }
}
