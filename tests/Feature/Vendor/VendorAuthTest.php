<?php

namespace Tests\Feature\Vendor;

use App\Models\TeamMember;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class VendorAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_vendor_can_register_without_country(): void
    {
        $this->postJson('/api/vendor/register', [
            'name' => 'New Vendor',
            'phone' => '+43123456789',
            'email' => 'vendor@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
            ->assertCreated()
            ->assertJsonStructure(['user', 'token'])
            ->assertJsonPath('user.slug', 'new-vendor');

        $this->assertDatabaseHas('vendors', [
            'name' => 'New Vendor',
            'email' => 'vendor@example.com',
            'country' => null,
            'slug' => 'new-vendor',
        ]);
    }

    public function test_vendor_registration_generates_a_unique_slug_for_duplicate_names(): void
    {
        Vendor::factory()->create([
            'name' => 'New Vendor',
            'restaurant_name' => null,
            'slug' => 'new-vendor',
        ]);

        $this->postJson('/api/vendor/register', [
            'name' => 'New Vendor',
            'phone' => '+43123456780',
            'email' => 'another-vendor@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
            ->assertCreated()
            ->assertJsonPath('user.slug', 'new-vendor-2');

        $this->assertDatabaseHas('vendors', [
            'email' => 'another-vendor@example.com',
            'slug' => 'new-vendor-2',
        ]);
    }

    public function test_vendor_owner_can_change_password(): void
    {
        $vendor = Vendor::factory()->create(['password' => 'old-password']);
        $token = $vendor->createToken('test')->plainTextToken;

        $this->postJson('/api/vendor/profile/password', [
            'current_password' => 'old-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ], ['Authorization' => "Bearer {$token}"])
            ->assertOk()
            ->assertJsonPath('message', 'Password changed successfully.');

        $this->assertTrue(Hash::check('new-password', $vendor->fresh()->password));
    }

    public function test_active_waiter_can_change_password(): void
    {
        $vendor = Vendor::factory()->create();
        $member = TeamMember::create([
            'vendor_id' => $vendor->id,
            'name' => 'Waiter User',
            'email' => 'waiter@example.com',
            'password' => 'old-password',
            'role' => 'waiter',
            'permissions' => TeamMember::defaultPermissions('waiter'),
            'status' => 'active',
            'joined_at' => now(),
        ]);
        $token = $member->createToken('staff', ['role:team_member', 'role:waiter'])->plainTextToken;

        $this->postJson('/api/vendor/profile/password', [
            'current_password' => 'old-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ], ['Authorization' => "Bearer {$token}"])
            ->assertOk()
            ->assertJsonPath('message', 'Password changed successfully.');

        $this->assertTrue(Hash::check('new-password', $member->fresh()->password));
    }

    public function test_active_kitchen_user_can_change_password(): void
    {
        $vendor = Vendor::factory()->create();
        $member = TeamMember::create([
            'vendor_id' => $vendor->id,
            'name' => 'Kitchen User',
            'email' => 'kitchen@example.com',
            'password' => 'old-password',
            'role' => 'kitchen',
            'permissions' => TeamMember::defaultPermissions('kitchen'),
            'status' => 'active',
            'joined_at' => now(),
        ]);
        $token = $member->createToken('staff', ['role:team_member', 'role:kitchen'])->plainTextToken;

        $this->postJson('/api/vendor/profile/password', [
            'current_password' => 'old-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ], ['Authorization' => "Bearer {$token}"])
            ->assertOk()
            ->assertJsonPath('message', 'Password changed successfully.');

        $this->assertTrue(Hash::check('new-password', $member->fresh()->password));
    }

    public function test_change_password_requires_correct_current_password(): void
    {
        $vendor = Vendor::factory()->create(['password' => 'old-password']);
        $token = $vendor->createToken('test')->plainTextToken;

        $this->postJson('/api/vendor/profile/password', [
            'current_password' => 'wrong-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ], ['Authorization' => "Bearer {$token}"])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('current_password');

        $this->assertTrue(Hash::check('old-password', $vendor->fresh()->password));
    }
}
