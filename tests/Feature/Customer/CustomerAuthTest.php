<?php

namespace Tests\Feature\Customer;

use App\Models\Customer;
use App\Services\SocialAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class CustomerAuthTest extends TestCase
{
    use RefreshDatabase;

    // ----------------------------------------------------------------
    // POST /api/customer/register
    // ----------------------------------------------------------------

    public function test_customer_can_register(): void
    {
        $response = $this->postJson('/api/customer/register', [
            'first_name'            => 'John',
            'last_name'             => 'Doe',
            'phone'                 => '+43123456789',
            'email'                 => 'john@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['user', 'token'])
            ->assertJsonPath('user.email', 'john@example.com')
            ->assertJsonPath('user.first_name', 'John')
            ->assertJsonPath('user.last_name', 'Doe');

        $this->assertDatabaseHas('customers', [
            'email'      => 'john@example.com',
            'first_name' => 'John',
            'last_name'  => 'Doe',
        ]);
    }

    public function test_register_requires_all_fields(): void
    {
        $response = $this->postJson('/api/customer/register', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['first_name', 'last_name', 'phone', 'email', 'password']);
    }

    public function test_register_rejects_duplicate_email(): void
    {
        Customer::factory()->create(['email' => 'john@example.com']);

        $response = $this->postJson('/api/customer/register', [
            'first_name'            => 'John',
            'last_name'             => 'Doe',
            'phone'                 => '+43999999999',
            'email'                 => 'john@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_register_rejects_duplicate_phone(): void
    {
        Customer::factory()->create(['phone' => '+43123456789']);

        $response = $this->postJson('/api/customer/register', [
            'first_name'            => 'Jane',
            'last_name'             => 'Doe',
            'phone'                 => '+43123456789',
            'email'                 => 'jane@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['phone']);
    }

    public function test_register_rejects_short_password(): void
    {
        $response = $this->postJson('/api/customer/register', [
            'first_name'            => 'John',
            'last_name'             => 'Doe',
            'phone'                 => '+43123456789',
            'email'                 => 'john@example.com',
            'password'              => 'short',
            'password_confirmation' => 'short',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    // ----------------------------------------------------------------
    // POST /api/customer/login
    // ----------------------------------------------------------------

    public function test_customer_can_login(): void
    {
        $customer = Customer::factory()->create([
            'email' => 'john@example.com',
        ]);

        $response = $this->postJson('/api/customer/login', [
            'email'    => 'john@example.com',
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['user', 'token']);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        Customer::factory()->create(['email' => 'john@example.com']);

        $response = $this->postJson('/api/customer/login', [
            'email'    => 'john@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_login_fails_with_nonexistent_email(): void
    {
        $response = $this->postJson('/api/customer/login', [
            'email'    => 'nonexistent@example.com',
            'password' => 'password',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    // ----------------------------------------------------------------
    // POST /api/customer/social/register
    // ----------------------------------------------------------------

    public function test_customer_can_register_via_social(): void
    {
        $this->mockSocialAuth('google', 'valid-token', [
            'provider_id' => 'google-id-123',
            'email'       => 'social@example.com',
            'first_name'  => 'Social',
            'last_name'   => 'User',
        ]);

        $response = $this->postJson('/api/customer/social/register', [
            'provider'     => 'google',
            'access_token' => 'valid-token',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['user', 'token'])
            ->assertJsonPath('user.email', 'social@example.com');

        $this->assertDatabaseHas('customers', [
            'email'           => 'social@example.com',
            'social_provider' => 'google',
        ]);
    }

    public function test_social_register_links_existing_email(): void
    {
        $customer = Customer::factory()->create(['email' => 'existing@example.com']);

        $this->mockSocialAuth('google', 'valid-token', [
            'provider_id' => 'google-id-456',
            'email'       => 'existing@example.com',
            'first_name'  => 'Existing',
            'last_name'   => 'User',
        ]);

        $response = $this->postJson('/api/customer/social/register', [
            'provider'     => 'google',
            'access_token' => 'valid-token',
        ]);

        $response->assertOk();

        $customer->refresh();
        $this->assertEquals('google', $customer->social_provider);
    }

    public function test_social_register_returns_existing_social_user(): void
    {
        Customer::factory()->social('google')->create([
            'email'              => 'social@example.com',
            'social_provider_id' => 'google-id-789',
        ]);

        $this->mockSocialAuth('google', 'valid-token', [
            'provider_id' => 'google-id-789',
            'email'       => 'social@example.com',
            'first_name'  => 'Social',
            'last_name'   => 'User',
        ]);

        $response = $this->postJson('/api/customer/social/register', [
            'provider'     => 'google',
            'access_token' => 'valid-token',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['user', 'token']);

        // Only 1 customer should exist
        $this->assertEquals(1, Customer::count());
    }

    public function test_social_register_rejects_invalid_provider(): void
    {
        $response = $this->postJson('/api/customer/social/register', [
            'provider'     => 'twitter',
            'access_token' => 'some-token',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['provider']);
    }

    public function test_social_register_rejects_invalid_token(): void
    {
        $mock = Mockery::mock(SocialAuthService::class);
        $mock->shouldReceive('verify')
            ->with('google', 'bad-token')
            ->andThrow(new \Exception('Invalid Google access token.'));
        $this->app->instance(SocialAuthService::class, $mock);

        $response = $this->postJson('/api/customer/social/register', [
            'provider'     => 'google',
            'access_token' => 'bad-token',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['access_token']);
    }

    public function test_social_register_works_with_facebook(): void
    {
        $this->mockSocialAuth('facebook', 'fb-token', [
            'provider_id' => 'fb-id-123',
            'email'       => 'fb@example.com',
            'first_name'  => 'FB',
            'last_name'   => 'User',
        ]);

        $response = $this->postJson('/api/customer/social/register', [
            'provider'     => 'facebook',
            'access_token' => 'fb-token',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['user', 'token']);

        $this->assertDatabaseHas('customers', [
            'email'           => 'fb@example.com',
            'social_provider' => 'facebook',
        ]);
    }

    // ----------------------------------------------------------------
    // POST /api/customer/social/login
    // ----------------------------------------------------------------

    public function test_customer_can_login_via_social(): void
    {
        Customer::factory()->social('google')->create([
            'social_provider_id' => 'google-id-login',
        ]);

        $this->mockSocialAuth('google', 'valid-token', [
            'provider_id' => 'google-id-login',
            'email'       => 'test@example.com',
            'first_name'  => 'Test',
            'last_name'   => 'User',
        ]);

        $response = $this->postJson('/api/customer/social/login', [
            'provider'     => 'google',
            'access_token' => 'valid-token',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['user', 'token']);
    }

    public function test_social_login_fails_for_unknown_provider_id(): void
    {
        $this->mockSocialAuth('google', 'valid-token', [
            'provider_id' => 'unknown-id',
            'email'       => 'test@example.com',
            'first_name'  => 'Test',
            'last_name'   => 'User',
        ]);

        $response = $this->postJson('/api/customer/social/login', [
            'provider'     => 'google',
            'access_token' => 'valid-token',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['provider']);
    }

    public function test_social_login_rejects_invalid_token(): void
    {
        $mock = Mockery::mock(SocialAuthService::class);
        $mock->shouldReceive('verify')
            ->with('google', 'bad-token')
            ->andThrow(new \Exception('Invalid Google access token.'));
        $this->app->instance(SocialAuthService::class, $mock);

        $response = $this->postJson('/api/customer/social/login', [
            'provider'     => 'google',
            'access_token' => 'bad-token',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['access_token']);
    }

    // ----------------------------------------------------------------
    // GET /api/customer/me
    // ----------------------------------------------------------------

    public function test_me_returns_authenticated_customer(): void
    {
        $customer = Customer::factory()->create();
        $token = $customer->createToken('test', ['role:customer'])->plainTextToken;

        $response = $this->getJson('/api/customer/me', [
            'Authorization' => "Bearer {$token}",
            'Accept'        => 'application/json',
        ]);

        $response->assertOk()
            ->assertJsonPath('email', $customer->email);
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/customer/me', ['Accept' => 'application/json'])
            ->assertUnauthorized();
    }

    // ----------------------------------------------------------------
    // POST /api/customer/logout
    // ----------------------------------------------------------------

    public function test_customer_can_logout(): void
    {
        $customer = Customer::factory()->create();
        $token = $customer->createToken('test', ['role:customer'])->plainTextToken;

        $response = $this->postJson('/api/customer/logout', [], [
            'Authorization' => "Bearer {$token}",
            'Accept'        => 'application/json',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Logged out.');
    }

    // ----------------------------------------------------------------
    // POST /api/customer/logout-all
    // ----------------------------------------------------------------

    public function test_customer_can_logout_all_devices(): void
    {
        $customer = Customer::factory()->create();
        $customer->createToken('device-1', ['role:customer']);
        $customer->createToken('device-2', ['role:customer']);
        $token = $customer->createToken('device-3', ['role:customer'])->plainTextToken;

        $response = $this->postJson('/api/customer/logout-all', [], [
            'Authorization' => "Bearer {$token}",
            'Accept'        => 'application/json',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Logged out from all devices.');

        $this->assertEquals(0, $customer->tokens()->count());
    }

    // ----------------------------------------------------------------
    // Helper
    // ----------------------------------------------------------------

    protected function mockSocialAuth(string $provider, string $token, array $returnData): void
    {
        $mock = Mockery::mock(SocialAuthService::class);
        $mock->shouldReceive('verify')
            ->with($provider, $token)
            ->andReturn($returnData);
        $this->app->instance(SocialAuthService::class, $mock);
    }
}
