<?php

namespace Tests\Feature\Orders;

use App\Models\Order;
use App\Models\Vendor;
use App\Models\VendorSetting;
use App\Services\InvoiceNumberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Invoice numbers used to be minted by the receipt endpoints, which meant an
 * order nobody opened never got one and the sequence followed viewing order
 * rather than payment order. These guard the move to assignment at payment.
 */
class InvoiceNumberAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private function vendorWithSettings(array $overrides = []): Vendor
    {
        $vendor = Vendor::factory()->create();

        VendorSetting::factory()->create([
            'vendor_id' => $vendor->id,
            ...$overrides,
        ]);

        return $vendor;
    }

    public function test_confirming_payment_assigns_an_invoice_number(): void
    {
        $vendor = $this->vendorWithSettings(['invoice_prefix' => 'ACME', 'next_invoice_number' => 2001]);

        $order = Order::factory()->create(['vendor_id' => $vendor->id]);
        $this->assertNull($order->invoice_number);

        $order->update(['payment_received' => true, 'payment_confirmed_at' => now()]);

        $this->assertSame('ACME-0002001', $order->fresh()->invoice_number);
        $this->assertSame(2002, (int) $vendor->vendorSetting->fresh()->next_invoice_number);
    }

    public function test_an_order_created_already_paid_is_numbered(): void
    {
        $vendor = $this->vendorWithSettings(['invoice_prefix' => 'INV', 'next_invoice_number' => 1001]);

        $order = Order::factory()->create([
            'vendor_id' => $vendor->id,
            'payment_received' => true,
            'payment_confirmed_at' => now(),
        ]);

        $this->assertSame('INV-0001001', $order->fresh()->invoice_number);
    }

    public function test_unpaid_orders_are_not_numbered(): void
    {
        $vendor = $this->vendorWithSettings();

        $order = Order::factory()->create(['vendor_id' => $vendor->id]);
        $order->update(['status' => Order::STATUS_CONFIRMED, 'confirmed_at' => now()]);

        $this->assertNull($order->fresh()->invoice_number);
    }

    public function test_numbers_follow_payment_order_and_are_never_reused(): void
    {
        $vendor = $this->vendorWithSettings(['invoice_prefix' => 'INV', 'next_invoice_number' => 1001]);

        $orders = Order::factory()->count(5)->create(['vendor_id' => $vendor->id]);

        // Pay them in an order unrelated to how they were created.
        foreach ($orders->shuffle() as $index => $order) {
            $order->update(['payment_confirmed_at' => now()->addSeconds($index)]);
        }

        $numbers = Order::whereIn('id', $orders->pluck('id'))
            ->orderBy('payment_confirmed_at')
            ->pluck('invoice_number');

        $this->assertCount(5, $numbers->unique(), 'invoice numbers must be unique');
        $this->assertSame([
            'INV-0001001',
            'INV-0001002',
            'INV-0001003',
            'INV-0001004',
            'INV-0001005',
        ], $numbers->all());
    }

    public function test_a_second_payment_update_does_not_renumber_an_order(): void
    {
        $vendor = $this->vendorWithSettings(['next_invoice_number' => 1001]);

        $order = Order::factory()->create(['vendor_id' => $vendor->id]);
        $order->update(['payment_confirmed_at' => now()]);
        $assigned = $order->fresh()->invoice_number;

        $order->update(['payment_confirmed_at' => now()->addHour()]);

        $this->assertSame($assigned, $order->fresh()->invoice_number);
        $this->assertSame(1002, (int) $vendor->vendorSetting->fresh()->next_invoice_number);
    }

    public function test_a_vendor_who_never_saved_settings_still_gets_distinct_numbers(): void
    {
        $vendor = Vendor::factory()->create();
        $this->assertDatabaseMissing('vendor_settings', ['vendor_id' => $vendor->id]);

        $first = Order::factory()->create(['vendor_id' => $vendor->id]);
        $second = Order::factory()->create(['vendor_id' => $vendor->id]);

        $first->update(['payment_confirmed_at' => now()]);
        $second->update(['payment_confirmed_at' => now()]);

        $this->assertSame('INV-0001001', $first->fresh()->invoice_number);
        $this->assertSame('INV-0001002', $second->fresh()->invoice_number);
        $this->assertDatabaseHas('vendor_settings', ['vendor_id' => $vendor->id]);
    }

    public function test_allocation_is_scoped_per_vendor(): void
    {
        $first = $this->vendorWithSettings(['invoice_prefix' => 'AAA', 'next_invoice_number' => 1001]);
        $second = $this->vendorWithSettings(['invoice_prefix' => 'BBB', 'next_invoice_number' => 5000]);

        $firstOrder = Order::factory()->create(['vendor_id' => $first->id]);
        $secondOrder = Order::factory()->create(['vendor_id' => $second->id]);

        $firstOrder->update(['payment_confirmed_at' => now()]);
        $secondOrder->update(['payment_confirmed_at' => now()]);

        $this->assertSame('AAA-0001001', $firstOrder->fresh()->invoice_number);
        $this->assertSame('BBB-0005000', $secondOrder->fresh()->invoice_number);
    }

    /**
     * The unique index used to cover invoice_number alone rather than
     * (vendor_id, invoice_number). Every vendor who never customised Settings
     * starts on the same INV/1001, so the second one to confirm a payment
     * collided with the first vendor's number — and because allocation is a
     * savepoint inside the caller's transaction, the rollback took the counter
     * increment with it and the retry recomputed the same doomed number.
     */
    public function test_two_vendors_on_the_default_prefix_do_not_collide(): void
    {
        $first = $this->vendorWithSettings(['invoice_prefix' => 'INV', 'next_invoice_number' => 1001]);
        $second = $this->vendorWithSettings(['invoice_prefix' => 'INV', 'next_invoice_number' => 1001]);

        $firstOrder = Order::factory()->create(['vendor_id' => $first->id]);
        $secondOrder = Order::factory()->create(['vendor_id' => $second->id]);

        $firstOrder->update(['payment_confirmed_at' => now()]);
        $secondOrder->update(['payment_confirmed_at' => now()]);

        // Same number, different vendors — legitimate, and previously rejected.
        $this->assertSame('INV-0001001', $firstOrder->fresh()->invoice_number);
        $this->assertSame('INV-0001001', $secondOrder->fresh()->invoice_number);
    }

    public function test_a_vendor_cannot_reuse_one_of_its_own_numbers(): void
    {
        $vendor = $this->vendorWithSettings(['invoice_prefix' => 'DUP', 'next_invoice_number' => 1001]);

        $paid = Order::factory()->create(['vendor_id' => $vendor->id]);
        $paid->update(['payment_confirmed_at' => now()]);
        $this->assertSame('DUP-0001001', $paid->fresh()->invoice_number);

        // Narrowing the constraint must not weaken it within a single vendor.
        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);

        DB::table('orders')->where('id', Order::factory()->create([
            'vendor_id' => $vendor->id,
        ])->id)->update(['invoice_number' => 'DUP-0001001']);
    }

    public function test_orders_paid_before_this_change_are_numbered_on_first_receipt_view(): void
    {
        $vendor = $this->vendorWithSettings(['invoice_prefix' => 'OLD', 'next_invoice_number' => 1001]);

        $order = Order::factory()->create(['vendor_id' => $vendor->id]);

        // Bypass the model hook the way a legacy row looks: paid, unnumbered.
        DB::table('orders')->where('id', $order->id)->update([
            'payment_received' => true,
            'payment_confirmed_at' => now(),
        ]);

        $legacy = Order::find($order->id);
        $this->assertNull($legacy->invoice_number);

        $numbers = app(InvoiceNumberService::class);
        $this->assertSame('OLD-0001001', $numbers->forOrder($legacy));
        // Reading it a second time must not consume another number.
        $this->assertSame('OLD-0001001', $numbers->forOrder($legacy->fresh()));
        $this->assertSame(1002, (int) $vendor->vendorSetting->fresh()->next_invoice_number);
    }

    public function test_a_partially_selected_order_is_left_alone(): void
    {
        $vendor = $this->vendorWithSettings(['next_invoice_number' => 1001]);

        $order = Order::factory()->create(['vendor_id' => $vendor->id]);
        $order->update(['payment_confirmed_at' => now()]);
        $assigned = $order->fresh()->invoice_number;

        // A select that omits invoice_number must not look like an unnumbered
        // order and overwrite the number already issued.
        $partial = Order::query()
            ->select(['id', 'vendor_id', 'payment_confirmed_at', 'status'])
            ->find($order->id);
        $partial->update(['payment_confirmed_at' => now()->addHour()]);

        $this->assertSame($assigned, $order->fresh()->invoice_number);
    }
}
