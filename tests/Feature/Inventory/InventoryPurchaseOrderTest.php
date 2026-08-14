<?php

namespace Tests\Feature\Inventory;

use App\Mail\InventoryPurchaseOrderMail;
use App\Models\InventorySettings;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class InventoryPurchaseOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_purchase_order_is_persisted_and_sent_using_vendor_currency(): void
    {
        Mail::fake();
        $vendor = Vendor::factory()->create(['country' => 'GB']);
        $item = $vendor->inventoryItems()->create([
            'name' => 'Tomatoes',
            'quantity' => 2,
            'unit' => 'kg',
            'min_stock' => 5,
            'reorder_quantity' => 10,
            'cost_per_unit' => 3.25,
            'track_stock' => true,
        ]);
        $this->settings($vendor, [[
            'id' => 'supplier-email',
            'name' => 'Fresh Supplier',
            'supportedIngredients' => ['Tomatoes'],
            'leadTime' => 2,
            'orderingMethod' => 'Email',
            'status' => 'active',
            'email' => 'orders@supplier.test',
        ]]);

        $response = $this->postJson(
            "/api/vendor/{$vendor->vendor_public_id}/inventory/purchase-orders",
            [
                'supplierId' => 'supplier-email',
                'inventoryItemId' => $item->id,
                'quantity' => 10,
                'estimatedDeliveryDate' => '2026-08-20',
                'notes' => 'Morning delivery',
            ],
            $this->vendorHeaders($vendor),
        )
            ->assertCreated()
            ->assertJsonPath('supplierName', 'Fresh Supplier')
            ->assertJsonPath('status', 'sent')
            ->assertJsonPath('currency', 'GBP')
            ->assertJsonPath('totalCost', 32.5);

        $publicId = $response->json('purchaseOrderPublicId');
        $this->assertStringStartsWith('PO-', $publicId);
        $this->assertDatabaseHas('inventory_purchase_orders', [
            'purchase_order_public_id' => $publicId,
            'inventory_item_id' => $item->id,
            'status' => 'sent',
            'currency' => 'GBP',
        ]);
        Mail::assertSent(
            InventoryPurchaseOrderMail::class,
            fn (InventoryPurchaseOrderMail $mail) => $mail->hasTo('orders@supplier.test')
                && $mail->purchaseOrder->purchase_order_public_id === $publicId
        );
    }

    public function test_api_purchase_order_posts_the_persisted_payload_to_supplier(): void
    {
        Http::fake(['supplier.test/*' => Http::response(['accepted' => true], 202)]);
        $vendor = Vendor::factory()->create();
        $item = $vendor->inventoryItems()->create([
            'name' => 'Basil',
            'quantity' => 1,
            'unit' => 'kg',
            'min_stock' => 2,
            'reorder_quantity' => 4,
            'cost_per_unit' => 6,
        ]);
        $this->settings($vendor, [[
            'id' => 'supplier-api',
            'name' => 'API Supplier',
            'supportedIngredients' => [],
            'ingredientConfigs' => [[
                'ingredientId' => (string) $item->id,
                'ingredientName' => 'Basil',
                'supplyUnit' => 'kg',
            ]],
            'leadTime' => 1,
            'orderingMethod' => 'API',
            'orderingUrl' => 'https://supplier.test/orders',
            'status' => 'active',
        ]]);

        $response = $this->postJson(
            "/api/vendor/{$vendor->id}/inventory/purchase-orders",
            [
                'supplierId' => 'supplier-api',
                'inventoryItemId' => $item->id,
                'quantity' => 5,
            ],
            $this->vendorHeaders($vendor),
        )->assertCreated()->assertJsonPath('status', 'sent');

        Http::assertSent(fn ($request) => $request->url() === 'https://supplier.test/orders'
            && $request['purchaseOrderId'] === $response->json('purchaseOrderPublicId')
            && $request['ingredient'] === 'Basil'
            && $request['quantity'] === 5.0
            && $request['unitCost'] === 6.0
            && $request['totalCost'] === 30.0
            && $request['currency'] === 'EUR');
    }

    public function test_phone_purchase_order_is_saved_for_manual_dispatch(): void
    {
        $vendor = Vendor::factory()->create();
        $item = $vendor->inventoryItems()->create([
            'name' => 'Milk',
            'quantity' => 1,
            'unit' => 'liter',
            'min_stock' => 2,
            'reorder_quantity' => 4,
            'cost_per_unit' => 2,
            'supplier' => 'Local Dairy',
        ]);
        $this->settings($vendor, [[
            'id' => 'supplier-phone',
            'name' => 'Local Dairy',
            'supportedIngredients' => [],
            'leadTime' => 1,
            'orderingMethod' => 'Phone',
            'phone' => '+43 1234567',
            'status' => 'active',
        ]]);

        $this->postJson(
            "/api/vendor/{$vendor->id}/inventory/purchase-orders",
            ['supplierId' => 'supplier-phone', 'inventoryItemId' => $item->id, 'quantity' => 4],
            $this->vendorHeaders($vendor),
        )
            ->assertCreated()
            ->assertJsonPath('status', 'manual_action_required');
    }

    private function settings(Vendor $vendor, array $suppliers): void
    {
        InventorySettings::create([
            'vendor_id' => $vendor->id,
            'settings' => ['suppliers' => $suppliers],
        ]);
    }

    private function vendorHeaders(Vendor $vendor): array
    {
        $token = $vendor->createToken('purchase-order-test')->plainTextToken;

        return ['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'];
    }
}
