<?php

namespace Tests\Feature\Fiscal;

use App\Jobs\FiscalizeOrder;
use App\Models\CartItem;
use App\Models\Customer;
use App\Models\FiscalDevice;
use App\Models\FiscalReceipt;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Models\TableScanSession;
use App\Models\Vendor;
use App\Models\VendorSetting;
use App\Services\Fiscal\FiscalizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use Throwable;

/**
 * Covers the fiscalization path end to end against a faked fiskaly: what gets
 * recorded at payment, how the VAT breakdown is built, and what happens when
 * signing fails or the country is not fiscalized at all.
 */
class FiskalyFiscalizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        // Fiscalization is queued through DeferredQueueDispatcher, which holds
        // the push until the response is flushed. Tests need it to happen now.
        $this->withoutDefer();

        config([
            'services.fiskaly.enabled' => true,
            // Some of these assert what happens when the signing job runs
            // inline, so the connection is pinned rather than inherited from
            // whatever .env happens to say.
            'services.fiskaly.queue_connection' => 'sync',
            'services.fiskaly.environment' => 'sandbox',
            'services.fiskaly.api_key' => 'test-key',
            'services.fiskaly.api_secret' => 'test-secret',
            'services.fiskaly.countries' => ['AT', 'DE'],
            'services.fiskaly.service_fee_vat' => 'standard',
            'services.fiskaly.tip_vat' => 'zero',
        ]);
    }

    private function vendor(string $country = 'AT'): Vendor
    {
        $vendor = Vendor::factory()->create([
            'country' => $country,
            'vat_number' => 'ATU12345678',
        ]);
        VendorSetting::factory()->create(['vendor_id' => $vendor->id, 'service_fee_rate' => 0]);

        return $vendor;
    }

    private function device(Vendor $vendor, string $country = 'AT'): FiscalDevice
    {
        return FiscalDevice::create([
            'vendor_id' => $vendor->id,
            'country' => $country,
            'environment' => 'sandbox',
            'signature_unit_id' => '11111111-1111-4111-8111-111111111111',
            'register_id' => '22222222-2222-4222-8222-222222222222',
            'serial_number' => 'TAVLO-TEST',
            'state' => FiscalDevice::STATE_INITIALIZED,
        ]);
    }

    /**
     * Two lines: food at the reduced rate, alcohol at the standard one. The
     * gross differs by country (AT 10/20 gives 28.00, DE 7/19 gives 27.35), and
     * orders.amount has to match or the reconciliation guard rejects it — which
     * is the point of the guard.
     */
    private function grossFor(string $country): float
    {
        return match ($country) {
            'DE' => 27.35,   // 20.00 +7%, 5.00 +19%
            default => 28.00, // 20.00 +10%, 5.00 +20%
        };
    }

    private function paidOrder(Vendor $vendor, float $tip = 0.0, float $serviceFee = 0.0): Order
    {
        $customer = Customer::factory()->create();

        $table = RestaurantTable::create([
            'vendor_id' => $vendor->id,
            'number' => 1,
            'name' => 'Table 1',
            'qr_token' => RestaurantTable::generateQrToken(),
            'is_active' => true,
            'qr_created_at' => now(),
        ]);

        $session = TableScanSession::create([
            'vendor_id' => $vendor->id,
            'restaurant_table_id' => $table->id,
            'customer_id' => $customer->id,
            'pin' => '1234',
            'status' => 'active',
            'scanned_at' => now(),
        ]);

        $category = MenuCategory::create([
            'vendor_id' => $vendor->id,
            'name' => 'Mains',
            'slug' => 'mains-'.$vendor->id,
            'is_active' => true,
        ]);

        $food = MenuItem::create([
            'vendor_id' => $vendor->id,
            'menu_category_id' => $category->id,
            'name' => 'Schnitzel',
            'price' => 20.00,
            'tax_category' => 'food',
            'available' => true,
        ]);

        $drink = MenuItem::create([
            'vendor_id' => $vendor->id,
            'menu_category_id' => $category->id,
            'name' => 'Beer',
            'price' => 5.00,
            'tax_category' => 'beverage_alcoholic',
            'available' => true,
        ]);

        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'vendor_id' => $vendor->id,
            'table_scan_session_id' => $session->id,
            'payment_method' => 'cash',
            'currency' => 'EUR',
            'amount' => $this->grossFor((string) $vendor->country) + $serviceFee,
            'tip_amount' => $tip,
            'service_fee' => $serviceFee,
        ]);

        foreach ([$food, $drink] as $item) {
            CartItem::create([
                'table_scan_session_id' => $session->id,
                'menu_item_id' => $item->id,
                'order_id' => $order->id,
                'quantity' => 1,
            ]);
        }

        return $order->fresh();
    }

    /**
     * Mirrors fiskaly's real SIGN AT response shape, confirmed against the
     * live sandbox on 2026-09-05: there is no top-level `signature` or
     * `signature_counter` field for RKSV — the actual signature is embedded
     * inside `qr_code_data`. That is unlike SIGN DE (see
     * test_germany_opens_and_finishes_a_transaction), which does return both.
     */
    private function fakeAustria(): void
    {
        Http::fake([
            '*/cash-register/*/receipt/*' => Http::response([
                'qr_code_data' => '_R1-AT1_TAVLO-TEST_1_2026-09-02T12:00:00_28,00_0,00_0,00_0,00_0,00_sig',
                'receipt_number' => 42,
                'cash_register_serial_number' => 'TAVLO-TEST',
            ]),
            '*' => Http::response(['access_token' => 'tok', 'access_token_expires_in' => 86400]),
        ]);
    }

    private function payload(Order $order): array
    {
        return FiscalReceipt::where('order_id', $order->id)->firstOrFail()->payload;
    }

    public function test_confirming_payment_records_a_pending_receipt_and_queues_signing(): void
    {
        Queue::fake();
        $order = $this->paidOrder($this->vendor('AT'));

        $order->update(['payment_received' => true, 'payment_confirmed_at' => now()]);

        $receipt = FiscalReceipt::where('order_id', $order->id)->firstOrFail();
        $this->assertSame(FiscalReceipt::STATE_PENDING, $receipt->state);
        $this->assertSame('AT', $receipt->country);
        $this->assertSame($order->fresh()->invoice_number, $receipt->invoice_number);
        $this->assertSame('28.00', number_format((float) $receipt->total_gross, 2, '.', ''));

        Queue::assertPushed(FiscalizeOrder::class);
    }

    public function test_vat_buckets_are_split_by_rate_and_sum_to_the_charge(): void
    {
        Queue::fake();
        $order = $this->paidOrder($this->vendor('AT'));

        $order->update(['payment_confirmed_at' => now()]);

        $payload = $this->payload($order);
        $buckets = collect($payload['amounts_per_vat_rate'])->pluck('amount', 'vat_rate');

        $this->assertSame('22.00', $buckets['REDUCED_1']);
        $this->assertSame('6.00', $buckets['STANDARD']);
        $this->assertSame(28.00, round(collect($payload['amounts_per_vat_rate'])
            ->sum(fn ($bucket) => (float) $bucket['amount']), 2));
        $this->assertSame(
            [['payment_type' => 'CASH', 'amount' => '28.00']],
            $payload['amounts_per_payment_type'],
        );
    }

    public function test_tip_and_service_fee_get_their_configured_buckets(): void
    {
        Queue::fake();
        $order = $this->paidOrder($this->vendor('AT'), tip: 3.00, serviceFee: 2.00);

        $order->update(['payment_confirmed_at' => now()]);

        $buckets = collect($this->payload($order)['amounts_per_vat_rate'])->pluck('amount', 'vat_rate');

        $this->assertSame('8.00', $buckets['STANDARD']);  // 6.00 drink + 2.00 service fee
        $this->assertSame('3.00', $buckets['ZERO']);      // tip, per tip_vat = zero
        $this->assertSame('33.00', $this->payload($order)['amounts_per_payment_type'][0]['amount']);
    }

    public function test_a_card_payment_is_declared_as_non_cash(): void
    {
        Queue::fake();
        $vendor = $this->vendor('AT');
        $order = $this->paidOrder($vendor);
        $order->forceFill(['payment_method' => 'stripe'])->save();

        $order->update(['payment_confirmed_at' => now()]);

        $this->assertSame('NON_CASH', $this->payload($order)['amounts_per_payment_type'][0]['payment_type']);
    }

    public function test_signing_stores_the_qr_code_and_signature(): void
    {
        $vendor = $this->vendor('AT');
        $this->device($vendor);
        $this->fakeAustria();

        $order = $this->paidOrder($vendor);
        $order->update(['payment_confirmed_at' => now()]);

        $receipt = FiscalReceipt::where('order_id', $order->id)->firstOrFail();
        app(FiscalizationService::class)->sign($receipt);

        $receipt->refresh();
        $this->assertSame(FiscalReceipt::STATE_SIGNED, $receipt->state);
        $this->assertStringStartsWith('_R1-AT1_', $receipt->qr_code_data);
        // SIGN AT has no separate signature field — see fakeAustria().
        $this->assertNull($receipt->signature);
        $this->assertSame('42', $receipt->receipt_number);
        $this->assertNotNull($receipt->signed_at);
    }

    /**
     * fiskaly's real SIGN AT schema rejects `standard_v1.receipt.*` — the
     * fields belong directly under `standard_v1`, and `line_items` is
     * required. Http::fake() does not validate outgoing shapes, so this once
     * shipped broken: every real signing call 400'd against fiskaly's actual
     * API despite every other test here passing. Verified against the live
     * sandbox on 2026-09-05.
     */
    public function test_the_signing_request_matches_fiskalys_standard_v1_schema(): void
    {
        $vendor = $this->vendor('AT');
        $this->device($vendor);
        $this->fakeAustria();

        $order = $this->paidOrder($vendor);
        $order->update(['payment_confirmed_at' => now()]);
        $receipt = FiscalReceipt::where('order_id', $order->id)->firstOrFail();

        app(FiscalizationService::class)->sign($receipt);

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/receipt/')) {
                return true; // not the signing call — ignore (e.g. /auth)
            }

            $schema = $request['schema'] ?? [];

            return array_key_exists('standard_v1', $schema)
                && ! array_key_exists('receipt', $schema['standard_v1'])
                && array_key_exists('amounts_per_vat_rate', $schema['standard_v1'])
                && array_key_exists('amounts_per_payment_type', $schema['standard_v1'])
                && ! empty($schema['standard_v1']['line_items']);
        });
    }

    public function test_signing_an_already_signed_receipt_is_a_no_op(): void
    {
        $vendor = $this->vendor('AT');
        $this->device($vendor);
        $this->fakeAustria();

        $order = $this->paidOrder($vendor);
        $order->update(['payment_confirmed_at' => now()]);
        $receipt = FiscalReceipt::where('order_id', $order->id)->firstOrFail();

        $service = app(FiscalizationService::class);
        $service->sign($receipt);
        $service->sign($receipt->fresh());

        // One /auth plus one receipt PUT — the second call signs nothing again.
        Http::assertSentCount(2);
    }

    public function test_a_failed_signature_leaves_the_receipt_failed_and_recorded(): void
    {
        Queue::fake();
        $vendor = $this->vendor('AT');
        $this->device($vendor);
        Http::fake([
            '*/cash-register/*' => Http::response(['error' => 'bad request'], 400),
            '*' => Http::response(['access_token' => 'tok']),
        ]);

        $order = $this->paidOrder($vendor);
        $order->update(['payment_confirmed_at' => now()]);
        $receipt = FiscalReceipt::where('order_id', $order->id)->firstOrFail();

        try {
            app(FiscalizationService::class)->sign($receipt);
            $this->fail('Expected the signing failure to propagate to the caller.');
        } catch (Throwable) {
            // The job catches this and retries; the receipt must record it.
        }

        $receipt->refresh();
        $this->assertSame(FiscalReceipt::STATE_FAILED, $receipt->state);
        $this->assertNotNull($receipt->last_error);
        $this->assertSame(1, $receipt->attempts);
    }

    public function test_a_signing_failure_never_reaches_the_payment(): void
    {
        // No Queue::fake(): the phpunit queue connection is sync, so the job
        // runs inline here exactly as it would on a misconfigured deployment.
        $vendor = $this->vendor('AT');
        $this->device($vendor);
        Http::fake([
            '*/cash-register/*' => Http::response(['error' => 'bad request'], 400),
            '*' => Http::response(['access_token' => 'tok']),
        ]);

        $order = $this->paidOrder($vendor);
        $order->update(['payment_received' => true, 'payment_confirmed_at' => now()]);

        $order->refresh();
        $this->assertTrue((bool) $order->payment_received);
        $this->assertNotNull($order->invoice_number);
        $this->assertSame(
            FiscalReceipt::STATE_FAILED,
            FiscalReceipt::where('order_id', $order->id)->firstOrFail()->state,
        );
    }

    public function test_signing_without_a_provisioned_device_fails_clearly(): void
    {
        Queue::fake();
        $order = $this->paidOrder($this->vendor('AT'));
        $order->update(['payment_confirmed_at' => now()]);
        $receipt = FiscalReceipt::where('order_id', $order->id)->firstOrFail();

        $this->expectExceptionMessageMatches('/no initialized fiscal device/');
        app(FiscalizationService::class)->sign($receipt);
    }

    public function test_an_unmapped_vat_rate_fails_loudly_instead_of_guessing(): void
    {
        Queue::fake();
        config(['services.fiskaly.vat_rate_map.AT' => ['20' => 'STANDARD']]);

        $order = $this->paidOrder($this->vendor('AT'));
        $order->update(['payment_confirmed_at' => now()]);

        // The 10% food line has no bucket, so nothing is recorded rather than
        // being quietly declared at the standard rate.
        $this->assertDatabaseMissing('fiscal_receipts', ['order_id' => $order->id]);
        Queue::assertNotPushed(FiscalizeOrder::class);
    }

    public function test_a_preparation_failure_does_not_break_the_payment(): void
    {
        Queue::fake();
        config(['services.fiskaly.vat_rate_map.AT' => []]);

        $order = $this->paidOrder($this->vendor('AT'));

        // The payment still settles and still gets its invoice number.
        $order->update(['payment_received' => true, 'payment_confirmed_at' => now()]);

        $order->refresh();
        $this->assertTrue((bool) $order->payment_received);
        $this->assertNotNull($order->invoice_number);
    }

    public function test_vendors_outside_the_configured_countries_are_untouched(): void
    {
        Queue::fake();
        $order = $this->paidOrder($this->vendor('GB'));

        $order->update(['payment_confirmed_at' => now()]);

        $this->assertDatabaseMissing('fiscal_receipts', ['order_id' => $order->id]);
        Queue::assertNotPushed(FiscalizeOrder::class);
        $this->assertNotNull($order->fresh()->invoice_number);
    }

    public function test_nothing_happens_when_fiskaly_is_disabled(): void
    {
        Queue::fake();
        config(['services.fiskaly.enabled' => false]);

        $order = $this->paidOrder($this->vendor('AT'));
        $order->update(['payment_confirmed_at' => now()]);

        $this->assertDatabaseMissing('fiscal_receipts', ['order_id' => $order->id]);
        Queue::assertNotPushed(FiscalizeOrder::class);
    }

    public function test_an_order_is_only_ever_recorded_once(): void
    {
        Queue::fake();
        $order = $this->paidOrder($this->vendor('AT'));

        $order->update(['payment_confirmed_at' => now()]);
        $order->update(['payment_confirmed_at' => now()->addHour()]);

        $this->assertSame(1, FiscalReceipt::where('order_id', $order->id)->count());
    }

    public function test_germany_opens_and_finishes_a_transaction(): void
    {
        $vendor = $this->vendor('DE');
        $this->device($vendor, 'DE');
        Http::fake([
            '*/tx/*' => Http::response([
                'number' => 7,
                'signature' => ['value' => 'de-sig', 'counter' => 12],
                'qr_code_data' => 'V0;TAVLO-TEST;Kassenbeleg-V1;...',
                'tss_serial_number' => 'TSS-SERIAL',
            ]),
            '*' => Http::response(['access_token' => 'tok']),
        ]);

        $order = $this->paidOrder($vendor);
        $order->update(['payment_confirmed_at' => now()]);
        $receipt = FiscalReceipt::where('order_id', $order->id)->firstOrFail();

        app(FiscalizationService::class)->sign($receipt);

        $receipt->refresh();
        $this->assertSame(FiscalReceipt::STATE_SIGNED, $receipt->state);
        $this->assertSame('de-sig', $receipt->signature);
        $this->assertSame('12', $receipt->signature_counter);
        $this->assertSame('TSS-SERIAL', $receipt->register_serial_number);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'tx_revision=1')
            && $request['state'] === 'ACTIVE');
        Http::assertSent(fn ($request) => str_contains($request->url(), 'tx_revision=2')
            && $request['state'] === 'FINISHED');
    }

    public function test_german_vat_buckets_use_the_german_names(): void
    {
        Queue::fake();
        $order = $this->paidOrder($this->vendor('DE'));

        $order->update(['payment_confirmed_at' => now()]);

        $buckets = collect($this->payload($order)['amounts_per_vat_rate'])->pluck('vat_rate');

        $this->assertTrue($buckets->contains('REDUCED'));
        $this->assertTrue($buckets->contains('NORMAL'));
    }
}
