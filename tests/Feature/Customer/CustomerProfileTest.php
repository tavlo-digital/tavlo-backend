<?php

namespace Tests\Feature\Customer;

use App\Models\Customer;
use App\Models\CustomerLoyaltyPoint;
use App\Models\Order;
use App\Models\Vendor;
use App\Models\VendorSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerProfileTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;
    private array $headers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->customer = Customer::factory()->create();
        $token = $this->customer->createToken('test', ['role:customer'])->plainTextToken;
        $this->headers = [
            'Authorization' => "Bearer {$token}",
            'Accept'        => 'application/json',
        ];
    }

    // ----------------------------------------------------------------
    // GET /api/customer/profile
    // ----------------------------------------------------------------

    public function test_customer_can_get_profile(): void
    {
        $response = $this->getJson('/api/customer/profile', $this->headers);

        $response->assertOk()
            ->assertJsonStructure([
                'profile',
                'recent_restaurants',
                'loyalty_overview',
            ]);
    }

    public function test_profile_shows_recent_restaurants(): void
    {
        $vendor = Vendor::factory()->create();
        VendorSetting::factory()->create(['vendor_id' => $vendor->id]);
        Order::factory()->create([
            'customer_id' => $this->customer->id,
            'vendor_id'   => $vendor->id,
        ]);

        $response = $this->getJson('/api/customer/profile', $this->headers);

        $response->assertOk()
            ->assertJsonCount(1, 'recent_restaurants');
    }

    public function test_profile_requires_auth(): void
    {
        $this->getJson('/api/customer/profile', ['Accept' => 'application/json'])
            ->assertUnauthorized();
    }

    // ----------------------------------------------------------------
    // PATCH /api/customer/profile
    // ----------------------------------------------------------------

    public function test_customer_can_update_profile(): void
    {
        $response = $this->patchJson('/api/customer/profile', [
            'gender'        => 'male',
            'date_of_birth' => '1990-01-15',
            'address'       => '123 Main St',
        ], $this->headers);

        $response->assertOk()
            ->assertJsonPath('message', 'Profile updated.');

        $this->customer->refresh();
        $this->assertEquals('male', $this->customer->gender);
        $this->assertEquals('123 Main St', $this->customer->address);
    }

    public function test_profile_update_rejects_invalid_gender(): void
    {
        $response = $this->patchJson('/api/customer/profile', [
            'gender' => 'invalid',
        ], $this->headers);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['gender']);
    }

    public function test_profile_update_rejects_future_dob(): void
    {
        $response = $this->patchJson('/api/customer/profile', [
            'date_of_birth' => '2030-01-01',
        ], $this->headers);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['date_of_birth']);
    }

    // ----------------------------------------------------------------
    // POST /api/customer/profile/password
    // ----------------------------------------------------------------

    public function test_customer_can_change_password(): void
    {
        $response = $this->postJson('/api/customer/profile/password', [
            'current_password'      => 'password',
            'password'              => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ], $this->headers);

        $response->assertOk()
            ->assertJsonPath('message', 'Password changed successfully.');
    }

    public function test_change_password_fails_with_wrong_current(): void
    {
        $response = $this->postJson('/api/customer/profile/password', [
            'current_password'      => 'wrong-password',
            'password'              => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ], $this->headers);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['current_password']);
    }
}
