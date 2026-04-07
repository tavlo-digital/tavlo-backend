<?php

namespace Tests\Feature\Billing;

use App\Models\PaymentMethod;
use App\Models\Subscription;
use App\Models\SubscriptionEvent;
use App\Models\SubscriptionPlan;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingTest extends TestCase
{
    use RefreshDatabase;

    private Vendor $vendor;
    private SubscriptionPlan $planMonthly;
    private SubscriptionPlan $planYearly;
    private Subscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vendor = Vendor::factory()->create(['country' => 'Austria']);

        $this->planMonthly = SubscriptionPlan::create([
            'name'          => 'Monthly',
            'monthly_price' => 49.00,
            'yearly_price'  => 490.00,
            'max_users'     => 999,
            'is_active'     => true,
        ]);

        $this->planYearly = SubscriptionPlan::create([
            'name'          => 'Yearly',
            'monthly_price' => 49.00,
            'yearly_price'  => 490.00,
            'max_users'     => 999,
            'is_active'     => true,
        ]);

        $this->subscription = Subscription::create([
            'vendor_id'         => $this->vendor->id,
            'plan_id'           => $this->planMonthly->id,
            'status'            => 'active',
            'billing_cycle'     => 'monthly',
            'start_date'        => now()->toDateString(),
            'next_billing_date' => now()->addMonth()->toDateString(),
            'auto_renew'        => true,
        ]);
    }

    private function authHeaders(): array
    {
        $token = $this->vendor->createToken('test')->plainTextToken;
        return ['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'];
    }

    // ----------------------------------------------------------------
    // GET /api/vendor/{vendorId}/billing
    // ----------------------------------------------------------------

    public function test_can_get_billing_details(): void
    {
        $response = $this->getJson(
            "/api/vendor/{$this->vendor->vendor_public_id}/billing",
            $this->authHeaders()
        );

        $response->assertOk()
            ->assertJsonStructure([
                'subscriptionId',
                'planName',
                'billingCycle',
                'status',
                'price',
                'interval',
                'nextBillingDate',
                'autoRenew',
            ])
            ->assertJsonPath('planName', 'Monthly')
            ->assertJsonPath('billingCycle', 'monthly')
            ->assertJsonPath('status', 'active');
    }

    public function test_billing_requires_authentication(): void
    {
        $this->getJson("/api/vendor/{$this->vendor->vendor_public_id}/billing")
            ->assertUnauthorized();
    }

    public function test_cannot_access_another_vendors_billing(): void
    {
        $otherVendor = Vendor::factory()->create();

        $response = $this->getJson(
            "/api/vendor/{$otherVendor->vendor_public_id}/billing",
            $this->authHeaders()
        );

        $response->assertForbidden();
    }

    // ----------------------------------------------------------------
    // GET /api/vendor/{vendorId}/billing/invoices
    // ----------------------------------------------------------------

    public function test_can_get_invoices(): void
    {
        $response = $this->getJson(
            "/api/vendor/{$this->vendor->vendor_public_id}/billing/invoices",
            $this->authHeaders()
        );

        $response->assertOk()
            ->assertJsonStructure(['data', 'total']);
    }

    // ----------------------------------------------------------------
    // GET /api/vendor/{vendorId}/billing/usage
    // ----------------------------------------------------------------

    public function test_can_get_usage_stats(): void
    {
        $response = $this->getJson(
            "/api/vendor/{$this->vendor->vendor_public_id}/billing/usage",
            $this->authHeaders()
        );

        $response->assertOk()
            ->assertJsonStructure([
                'activeTables',
                'ordersThisMonth',
                'staffAccounts',
                'qrCodes',
            ]);
    }

    // ----------------------------------------------------------------
    // POST /api/vendor/{vendorId}/billing/upgrade
    // ----------------------------------------------------------------

    public function test_can_upgrade_plan(): void
    {
        $response = $this->postJson(
            "/api/vendor/{$this->vendor->vendor_public_id}/billing/upgrade",
            ['planId' => $this->planYearly->id],
            $this->authHeaders()
        );

        $response->assertOk()
            ->assertJsonPath('message', 'Plan upgraded successfully.');

        $this->assertDatabaseHas('subscriptions', [
            'id'      => $this->subscription->id,
            'plan_id' => $this->planYearly->id,
        ]);

        $this->assertDatabaseHas('subscription_events', [
            'subscription_id' => $this->subscription->id,
            'event_type'      => 'plan_changed',
            'new_plan_id'     => $this->planYearly->id,
        ]);
    }

    public function test_cannot_upgrade_to_same_plan(): void
    {
        $response = $this->postJson(
            "/api/vendor/{$this->vendor->vendor_public_id}/billing/upgrade",
            ['planId' => $this->planMonthly->id],
            $this->authHeaders()
        );

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'The selected plan is already active.');
    }

    public function test_upgrade_plan_validates_plan_id(): void
    {
        $response = $this->postJson(
            "/api/vendor/{$this->vendor->vendor_public_id}/billing/upgrade",
            ['planId' => 99999],
            $this->authHeaders()
        );

        $response->assertUnprocessable();
    }

    // ----------------------------------------------------------------
    // PATCH /api/vendor/{vendorId}/billing/cycle
    // ----------------------------------------------------------------

    public function test_can_change_billing_cycle_to_yearly(): void
    {
        $response = $this->patchJson(
            "/api/vendor/{$this->vendor->vendor_public_id}/billing/cycle",
            ['cycle' => 'yearly'],
            $this->authHeaders()
        );

        $response->assertOk()
            ->assertJsonPath('message', 'Billing cycle updated successfully.');

        $this->assertDatabaseHas('subscriptions', [
            'id'            => $this->subscription->id,
            'billing_cycle' => 'yearly',
        ]);

        $this->assertDatabaseHas('subscription_events', [
            'subscription_id' => $this->subscription->id,
            'event_type'      => 'cycle_changed',
        ]);
    }

    public function test_billing_cycle_must_be_valid(): void
    {
        $response = $this->patchJson(
            "/api/vendor/{$this->vendor->vendor_public_id}/billing/cycle",
            ['cycle' => 'weekly'],
            $this->authHeaders()
        );

        $response->assertUnprocessable();
    }

    // ----------------------------------------------------------------
    // POST /api/vendor/{vendorId}/billing/payment-method
    // ----------------------------------------------------------------

    public function test_can_update_payment_method(): void
    {
        $response = $this->postJson(
            "/api/vendor/{$this->vendor->vendor_public_id}/billing/payment-method",
            [
                'cardBrand'             => 'visa',
                'last4'                 => '4242',
                'expMonth'              => '12',
                'expYear'               => '2028',
                'stripePaymentMethodId' => 'pm_test_abc123',
                'billingEmail'          => 'billing@example.com',
            ],
            $this->authHeaders()
        );

        $response->assertOk()
            ->assertJsonPath('message', 'Payment method updated successfully.')
            ->assertJsonPath('paymentMethod.brand', 'visa')
            ->assertJsonPath('paymentMethod.last4', '4242');

        $this->assertDatabaseHas('payment_methods', [
            'vendor_id'               => $this->vendor->id,
            'card_brand'              => 'visa',
            'last4'                   => '4242',
            'stripe_payment_method_id' => 'pm_test_abc123',
            'is_default'              => 1,
        ]);
    }

    // ----------------------------------------------------------------
    // POST /api/vendor/{vendorId}/billing/cancel
    // ----------------------------------------------------------------

    public function test_can_cancel_subscription(): void
    {
        $response = $this->postJson(
            "/api/vendor/{$this->vendor->vendor_public_id}/billing/cancel",
            [],
            $this->authHeaders()
        );

        $response->assertOk()
            ->assertJsonPath('message', 'Subscription cancelled successfully.');

        $this->assertDatabaseHas('subscriptions', [
            'id'     => $this->subscription->id,
            'status' => 'cancelled',
        ]);

        $this->assertNotNull(
            Subscription::find($this->subscription->id)?->cancelled_at,
            'cancelled_at should be set'
        );

        $this->assertDatabaseHas('subscription_events', [
            'subscription_id' => $this->subscription->id,
            'event_type'      => 'subscription_cancelled',
        ]);
    }

    public function test_cannot_cancel_already_cancelled_subscription(): void
    {
        $this->subscription->update(['status' => 'cancelled', 'cancelled_at' => now()]);

        $response = $this->postJson(
            "/api/vendor/{$this->vendor->vendor_public_id}/billing/cancel",
            [],
            $this->authHeaders()
        );

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'This subscription is already cancelled.');
    }
}
