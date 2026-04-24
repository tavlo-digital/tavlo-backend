<?php

namespace Tests\Feature\Customer;

use App\Models\CartItem;
use App\Models\Customer;
use App\Models\MenuItem;
use App\Models\MenuCategory;
use App\Models\RestaurantTable;
use App\Models\TableScanSession;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TableCartTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;
    private Vendor $vendor;
    private RestaurantTable $table;
    private TableScanSession $session;
    private MenuItem $menuItem;
    private array $headers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = Customer::factory()->create(['first_name' => 'Alice', 'last_name' => 'Smith']);
        $this->vendor   = Vendor::factory()->create();

        $token = $this->customer->createToken('test', ['role:customer'])->plainTextToken;
        $this->headers = ['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'];

        $this->table = $this->vendor->restaurantTables()->create([
            'number'        => 1,
            'name'          => 'T1',
            'qr_token'      => RestaurantTable::generateQrToken(),
            'is_active'     => true,
            'qr_created_at' => now(),
        ]);

        $category = MenuCategory::create([
            'vendor_id' => $this->vendor->id,
            'name'      => 'Sides',
            'slug'      => 'sides-' . $this->vendor->id,
        ]);

        $this->menuItem = MenuItem::create([
            'vendor_id'        => $this->vendor->id,
            'menu_category_id' => $category->id,
            'name'             => 'Fries',
            'price'            => 3.50,
        ]);

        $this->session = TableScanSession::create([
            'vendor_id'           => $this->vendor->id,
            'restaurant_table_id' => $this->table->id,
            'customer_id'         => $this->customer->id,
            'pin'                 => '1234',
            'status'              => 'active',
            'scanned_at'          => now(),
        ]);
    }

    // ----------------------------------------------------------------
    // GET /api/customer/cart
    // ----------------------------------------------------------------

    public function test_get_cart_requires_auth(): void
    {
        $this->getJson('/api/customer/cart')
            ->assertUnauthorized();
    }

    public function test_get_cart_returns_422_when_no_active_session(): void
    {
        $this->session->update(['status' => 'closed']);

        $this->withHeaders($this->headers)
            ->getJson('/api/customer/cart')
            ->assertStatus(422)
            ->assertJson(['message' => 'No active table session found.']);
    }

    public function test_get_cart_returns_empty_cart_for_new_session(): void
    {
        $response = $this->withHeaders($this->headers)
            ->getJson('/api/customer/cart');

        $response->assertOk()
            ->assertJsonStructure(['people']);

        $this->assertCount(1, $response->json('people'));
        $this->assertSame([], $response->json('people.0.personal_items'));
    }

    public function test_get_cart_includes_all_people_at_table(): void
    {
        $other = Customer::factory()->create(['first_name' => 'Bob', 'last_name' => 'Jones']);
        TableScanSession::create([
            'vendor_id'           => $this->vendor->id,
            'restaurant_table_id' => $this->table->id,
            'customer_id'         => $other->id,
            'pin'                 => '',
            'status'              => 'active',
            'scanned_at'          => now(),
        ]);

        $response = $this->withHeaders($this->headers)
            ->getJson('/api/customer/cart');

        $response->assertOk();
        $this->assertCount(2, $response->json('people'));
    }

    public function test_get_cart_does_not_include_closed_sessions(): void
    {
        $closed = Customer::factory()->create();
        TableScanSession::create([
            'vendor_id'           => $this->vendor->id,
            'restaurant_table_id' => $this->table->id,
            'customer_id'         => $closed->id,
            'pin'                 => '',
            'status'              => 'closed',
            'scanned_at'          => now(),
        ]);

        $response = $this->withHeaders($this->headers)
            ->getJson('/api/customer/cart');

        $response->assertOk();
        $this->assertCount(1, $response->json('people'));
    }

    // ----------------------------------------------------------------
    // POST /api/customer/cart/items
    // ----------------------------------------------------------------

    public function test_add_item_requires_auth(): void
    {
        $this->postJson('/api/customer/cart/items', ['menu_item_id' => $this->menuItem->id])
            ->assertUnauthorized();
    }

    public function test_add_item_fails_without_active_session(): void
    {
        $this->session->update(['status' => 'closed']);

        $this->withHeaders($this->headers)
            ->postJson('/api/customer/cart/items', ['menu_item_id' => $this->menuItem->id])
            ->assertStatus(422);
    }

    public function test_add_item_validates_menu_item_id(): void
    {
        $this->withHeaders($this->headers)
            ->postJson('/api/customer/cart/items', ['menu_item_id' => 9999])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['menu_item_id']);
    }

    public function test_add_item_creates_cart_item(): void
    {
        $response = $this->withHeaders($this->headers)
            ->postJson('/api/customer/cart/items', [
                'menu_item_id' => $this->menuItem->id,
                'quantity'     => 2,
                'notes'        => 'No salt',
            ]);

        $response->assertCreated()
            ->assertJsonStructure(['id', 'quantity', 'notes', 'menu_item'])
            ->assertJsonPath('quantity', 2)
            ->assertJsonPath('notes', 'No salt')
            ->assertJsonPath('menu_item.name', 'Fries');

        $this->assertDatabaseHas('cart_items', [
            'table_scan_session_id' => $this->session->id,
            'menu_item_id'          => $this->menuItem->id,
            'quantity'              => 2,
        ]);
    }

    public function test_add_item_defaults_quantity_to_1(): void
    {
        $response = $this->withHeaders($this->headers)
            ->postJson('/api/customer/cart/items', ['menu_item_id' => $this->menuItem->id]);

        $response->assertCreated()->assertJsonPath('quantity', 1);
    }

    // ----------------------------------------------------------------
    // PATCH /api/customer/cart/items/{id}
    // ----------------------------------------------------------------

    public function test_update_item_changes_quantity_and_notes(): void
    {
        $item = CartItem::create([
            'table_scan_session_id' => $this->session->id,
            'menu_item_id'          => $this->menuItem->id,
            'quantity'              => 1,
        ]);

        $this->withHeaders($this->headers)
            ->patchJson("/api/customer/cart/items/{$item->id}", ['quantity' => 3, 'notes' => 'Extra crispy'])
            ->assertOk()
            ->assertJsonPath('quantity', 3)
            ->assertJsonPath('notes', 'Extra crispy');
    }

    public function test_update_item_returns_404_for_another_sessions_item(): void
    {
        $other = Customer::factory()->create();
        $otherSession = TableScanSession::create([
            'vendor_id'           => $this->vendor->id,
            'restaurant_table_id' => $this->table->id,
            'customer_id'         => $other->id,
            'pin'                 => '',
            'status'              => 'active',
            'scanned_at'          => now(),
        ]);

        $item = CartItem::create([
            'table_scan_session_id' => $otherSession->id,
            'menu_item_id'          => $this->menuItem->id,
            'quantity'              => 1,
        ]);

        $this->withHeaders($this->headers)
            ->patchJson("/api/customer/cart/items/{$item->id}", ['quantity' => 5])
            ->assertNotFound();
    }

    // ----------------------------------------------------------------
    // DELETE /api/customer/cart/items/{id}
    // ----------------------------------------------------------------

    public function test_remove_item_deletes_own_item(): void
    {
        $item = CartItem::create([
            'table_scan_session_id' => $this->session->id,
            'menu_item_id'          => $this->menuItem->id,
            'quantity'              => 1,
        ]);

        $this->withHeaders($this->headers)
            ->deleteJson("/api/customer/cart/items/{$item->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('cart_items', ['id' => $item->id]);
    }

    public function test_remove_item_returns_404_for_foreign_item(): void
    {
        $other = Customer::factory()->create();
        $otherSession = TableScanSession::create([
            'vendor_id'           => $this->vendor->id,
            'restaurant_table_id' => $this->table->id,
            'customer_id'         => $other->id,
            'pin'                 => '',
            'status'              => 'active',
            'scanned_at'          => now(),
        ]);

        $item = CartItem::create([
            'table_scan_session_id' => $otherSession->id,
            'menu_item_id'          => $this->menuItem->id,
            'quantity'              => 1,
        ]);

        $this->withHeaders($this->headers)
            ->deleteJson("/api/customer/cart/items/{$item->id}")
            ->assertNotFound();
    }

    public function test_cart_response_contains_personal_items_with_customer_id(): void
    {
        CartItem::create([
            'table_scan_session_id' => $this->session->id,
            'menu_item_id'          => $this->menuItem->id,
            'quantity'              => 1,
        ]);

        $response = $this->withHeaders($this->headers)
            ->getJson('/api/customer/cart');

        $response->assertOk()
            ->assertJsonPath('people.0.customer_id', $this->customer->id)
            ->assertJsonPath('people.0.is_me', true)
            ->assertJsonPath('people.0.name', 'Alice Smith');

        $this->assertCount(1, $response->json('people.0.personal_items'));
    }
}
