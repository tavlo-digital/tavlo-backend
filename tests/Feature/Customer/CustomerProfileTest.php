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

    public function test_profile_includes_computed_order_counts(): void
    {
        Order::factory()->count(2)->create([
            'customer_id' => $this->customer->id,
            'created_at' => now(),
            'payment_received' => true,
            'payment_pending' => false,
            'status' => 'completed',
        ]);
        Order::factory()->create([
            'customer_id' => $this->customer->id,
            'created_at' => now()->subMonthNoOverflow(),
            'payment_received' => true,
            'payment_pending' => false,
            'status' => 'completed',
        ]);
        Order::factory()->create([
            'customer_id' => $this->customer->id,
            'created_at' => now(),
            'payment_received' => false,
            'payment_pending' => true,
            'status' => 'draft',
        ]);

        $response = $this->getJson('/api/customer/profile', $this->headers);

        $response->assertOk()
            ->assertJsonPath('profile.monthly_orders', 2)
            ->assertJsonPath('profile.orders_count', 3);
    }

    public function test_profile_shows_recent_restaurants(): void
    {
        $vendor = Vendor::factory()->create();
        VendorSetting::factory()->create(['vendor_id' => $vendor->id]);
        Order::factory()->create([
            'customer_id' => $this->customer->id,
            'vendor_id'   => $vendor->id,
            'payment_received' => true,
            'payment_pending' => false,
            'status' => 'completed',
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
            'first_name'    => 'Sara',
            'last_name'     => 'Khan',
            'gender'        => 'male',
            'date_of_birth' => '1990-01-15',
            'address'       => '123 Main St',
        ], $this->headers);

        $response->assertOk()
            ->assertJsonPath('message', 'Profile updated.');

        $this->customer->refresh();
        $this->assertEquals('Sara', $this->customer->first_name);
        $this->assertEquals('Khan', $this->customer->last_name);
        $this->assertEquals('male', $this->customer->gender);
        $this->assertEquals('123 Main St', $this->customer->address);
    }

    public function test_customer_can_update_profile_with_put(): void
    {
        $response = $this->putJson('/api/customer/profile', [
            'first_name'      => 'Amina',
            'last_name'       => 'Hassan',
            'gender'          => 'female',
            'date_of_birth'   => '1991-02-20',
            'address'         => '123 Main Street, Vienna',
            'profile_picture' => 'http://localhost:8000/media/customers/1/avatar/abc123.jpg',
        ], $this->headers);

        $response->assertOk()
            ->assertJsonPath('message', 'Profile updated.')
            ->assertJsonPath('user.first_name', 'Amina')
            ->assertJsonPath('user.last_name', 'Hassan');
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
    // PUT /api/customer/profile/change-phone
    // ----------------------------------------------------------------

    public function test_customer_can_change_phone_number(): void
    {
        $this->customer->update(['phone_verified' => true]);

        $response = $this->putJson('/api/customer/profile/change-phone', [
            'new_number' => '+43123456789',
        ], $this->headers);

        $response->assertOk()
            ->assertJsonPath('message', 'Phone number updated.')
            ->assertJsonPath('user.phone', '+43123456789')
            ->assertJsonPath('user.phone_verified', false);
    }

    public function test_change_phone_rejects_duplicate_number(): void
    {
        $other = Customer::factory()->create();

        $response = $this->putJson('/api/customer/profile/change-phone', [
            'new_number' => $other->phone,
        ], $this->headers);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['new_number']);
    }

    public function test_change_phone_requires_auth(): void
    {
        $this->putJson('/api/customer/profile/change-phone', [
            'new_number' => '+43123456789',
        ], ['Accept' => 'application/json'])
            ->assertUnauthorized();
    }

    // ----------------------------------------------------------------
    // PUT|POST /api/customer/profile/change-email
    // ----------------------------------------------------------------

    public function test_customer_can_change_email_with_put(): void
    {
        $this->customer->update(['email_verified_at' => now()]);

        $response = $this->putJson('/api/customer/profile/change-email', [
            'current_email' => $this->customer->email,
            'new_email'     => 'new-email@example.com',
        ], $this->headers);

        $response->assertOk()
            ->assertJsonPath('message', 'Email address updated.')
            ->assertJsonPath('user.email', 'new-email@example.com')
            ->assertJsonPath('user.email_verified_at', null);
    }

    public function test_customer_can_change_email_with_post(): void
    {
        $response = $this->postJson('/api/customer/profile/change-email', [
            'current_email' => $this->customer->email,
            'new_email'     => 'post-email@example.com',
        ], $this->headers);

        $response->assertOk()
            ->assertJsonPath('user.email', 'post-email@example.com');
    }

    public function test_change_email_rejects_wrong_current_email(): void
    {
        $response = $this->putJson('/api/customer/profile/change-email', [
            'current_email' => 'wrong@example.com',
            'new_email'     => 'new-email@example.com',
        ], $this->headers);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['current_email']);
    }

    public function test_change_email_rejects_duplicate_email(): void
    {
        $other = Customer::factory()->create();

        $response = $this->putJson('/api/customer/profile/change-email', [
            'current_email' => $this->customer->email,
            'new_email'     => $other->email,
        ], $this->headers);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['new_email']);
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
