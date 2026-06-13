<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorRequestChange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorLegalChangeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Vendor $vendor;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['name' => 'admin', 'label' => 'Admin']);
        $this->admin = User::factory()->create(['role_id' => $role->id]);
        $this->vendor = Vendor::factory()->create([
            'restaurant_name' => 'Old Restaurant',
            'legal_entity_name' => 'Old GmbH',
            'slug' => 'test-vendor',
        ]);
    }

    public function test_admin_can_approve_pending_legal_change(): void
    {
        $change = VendorRequestChange::create([
            'vendor_id' => $this->vendor->id,
            'restaurant_name' => 'New Restaurant',
            'legal_entity_name' => 'New GmbH',
            'business_registration_number' => 'FN123456a',
            'vat_number' => 'ATU12345678',
            'company_type' => 'AG',
            'status' => 'pending',
        ]);

        $this->actingAs($this->admin)
            ->post("/admin/vendor/{$this->vendor->slug}/changes/{$change->id}/approve")
            ->assertRedirect();

        $this->assertDatabaseHas('vendor_request_changes', [
            'id' => $change->id,
            'status' => 'approved',
            'checked_by' => $this->admin->id,
        ]);
        $this->assertDatabaseHas('vendors', [
            'id' => $this->vendor->id,
            'restaurant_name' => 'New Restaurant',
            'legal_entity_name' => 'New GmbH',
        ]);
        $this->assertDatabaseHas('vendor_settings', [
            'vendor_id' => $this->vendor->id,
            'company_type' => 'AG',
        ]);
    }

    public function test_admin_can_reject_pending_legal_change(): void
    {
        $change = VendorRequestChange::create([
            'vendor_id' => $this->vendor->id,
            'legal_entity_name' => 'New GmbH',
            'business_registration_number' => 'FN123456a',
            'vat_number' => 'ATU12345678',
            'status' => 'pending',
        ]);

        $this->actingAs($this->admin)
            ->post(
                "/admin/vendor/{$this->vendor->slug}/changes/{$change->id}/decline",
                ['admin_notes' => 'Registration could not be verified.']
            )
            ->assertRedirect();

        $this->assertDatabaseHas('vendor_request_changes', [
            'id' => $change->id,
            'status' => 'rejected',
            'checked_by' => $this->admin->id,
            'admin_notes' => 'Registration could not be verified.',
        ]);
    }
}
