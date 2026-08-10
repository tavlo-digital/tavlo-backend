<?php

namespace Tests\Feature\Vendor;

use App\Mail\TeamInvitationMail;
use App\Models\TeamMember;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TeamAccessTest extends TestCase
{
    use RefreshDatabase;

    private Vendor $vendor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->vendor = Vendor::factory()->create(['email' => 'owner@example.com']);
    }

    private function vendorHeaders(): array
    {
        $token = $this->vendor->createToken('test')->plainTextToken;

        return ['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'];
    }

    public function test_owner_can_invite_waiter_and_email_is_sent(): void
    {
        Mail::fake();

        $response = $this->postJson("/api/vendor/{$this->vendor->id}/team/invite", [
            'email' => 'waiter@example.com',
            'role' => 'waiter',
        ], $this->vendorHeaders());

        $response->assertCreated()
            ->assertJsonPath('email', 'waiter@example.com')
            ->assertJsonPath('role', 'waiter')
            ->assertJsonPath('status', 'invited');

        $member = TeamMember::where('email', 'waiter@example.com')->firstOrFail();
        $this->assertNotNull($member->invitation_token);

        Mail::assertSent(TeamInvitationMail::class, fn (TeamInvitationMail $mail) => $mail->member->is($member));
    }

    public function test_invite_rejects_existing_vendor_email_and_manager_role(): void
    {
        $this->postJson("/api/vendor/{$this->vendor->id}/team/invite", [
            'email' => 'owner@example.com',
            'role' => 'waiter',
        ], $this->vendorHeaders())->assertUnprocessable();

        $this->postJson("/api/vendor/{$this->vendor->id}/team/invite", [
            'email' => 'manager@example.com',
            'role' => 'manager',
        ], $this->vendorHeaders())->assertUnprocessable();
    }

    public function test_invited_staff_can_accept_and_login(): void
    {
        $member = TeamMember::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Kitchen User',
            'email' => 'kitchen@example.com',
            'role' => 'kitchen',
            'permissions' => TeamMember::defaultPermissions('kitchen'),
            'status' => 'invited',
            'invitation_token' => TeamMember::generateInvitationToken(),
            'invited_at' => now(),
        ]);

        $this->getJson("/api/vendor/team/invitations/{$member->invitation_token}")
            ->assertOk()
            ->assertJsonPath('email', 'kitchen@example.com')
            ->assertJsonPath('role', 'kitchen');

        $this->postJson("/api/vendor/team/invitations/{$member->invitation_token}/accept", [
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertOk();

        $member->refresh();
        $this->assertSame('active', $member->status);
        $this->assertNull($member->invitation_token);
        $this->assertTrue(Hash::check('password123', $member->password));

        $login = $this->postJson('/api/vendor/login', [
            'email' => 'kitchen@example.com',
            'password' => 'password123',
        ]);

        $login->assertOk()
            ->assertJsonPath('user.actorType', 'team_member')
            ->assertJsonPath('user.role', 'kitchen')
            ->assertJsonPath('user.vendorId', (string) $this->vendor->id)
            ->assertJsonPath('user.slug', $this->vendor->slug)
            ->assertJsonStructure(['token']);
    }

    public function test_staff_me_and_route_restrictions(): void
    {
        $member = TeamMember::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Waiter User',
            'email' => 'waiter@example.com',
            'password' => Hash::make('password123'),
            'role' => 'waiter',
            'permissions' => TeamMember::defaultPermissions('waiter'),
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $token = $member->createToken('staff', ['role:team_member', 'role:waiter'])->plainTextToken;
        $headers = ['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'];

        $this->getJson('/api/vendor/me', $headers)
            ->assertOk()
            ->assertJsonPath('data.actorType', 'team_member')
            ->assertJsonPath('data.role', 'waiter');

        $this->getJson("/api/vendor/{$this->vendor->id}/team", $headers)
            ->assertForbidden();
    }

    public function test_owner_can_resend_invite(): void
    {
        Mail::fake();

        $member = TeamMember::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Waiter User',
            'email' => 'waiter@example.com',
            'role' => 'waiter',
            'permissions' => TeamMember::defaultPermissions('waiter'),
            'status' => 'invited',
            'invitation_token' => 'old-token',
            'invited_at' => now()->subDay(),
        ]);

        $this->postJson(
            "/api/vendor/{$this->vendor->id}/team/{$member->id}/resend",
            [],
            $this->vendorHeaders()
        )->assertOk();

        $this->assertNotSame('old-token', $member->fresh()->invitation_token);
        Mail::assertSent(TeamInvitationMail::class);
    }
}
