<?php

namespace Tests\Feature\Vendor;

use App\Models\Customer;
use App\Models\Reservation;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReservationManagementTest extends TestCase
{
    use RefreshDatabase;

    private Vendor $vendor;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vendor = Vendor::factory()->create();
        $this->customer = Customer::factory()->create([
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => 'ada@example.com',
            'phone' => '+4312345678',
        ]);
    }

    public function test_vendor_can_list_and_filter_reservations_with_customer_details(): void
    {
        $reservation = $this->createReservation([
            'date' => '2026-08-20',
            'status' => 'pending',
        ]);
        $this->createReservation([
            'date' => '2026-08-21',
            'status' => 'confirmed',
            'reservation_public_id' => 'res_other',
        ]);

        $this->getJson(
            "/api/vendor/{$this->vendor->id}/reservations?status=pending&date=2026-08-20",
            $this->vendorHeaders()
        )
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', (string) $reservation->id)
            ->assertJsonPath('0.reservationPublicId', 'RES-NFPOA3R1')
            ->assertJsonPath('0.customer.name', 'Ada Lovelace')
            ->assertJsonPath('0.customer.email', 'ada@example.com')
            ->assertJsonPath('0.customer.phone', '+4312345678')
            ->assertJsonPath('0.customerNote', 'Window seat, please.')
            ->assertJsonPath('0.status', 'pending');
    }

    public function test_vendor_can_confirm_and_decline_a_reservation(): void
    {
        $this->createReservation();

        $reservationSelects = [];
        DB::listen(function ($query) use (&$reservationSelects): void {
            if (str_starts_with(strtolower(ltrim($query->sql)), 'select')
                && str_contains($query->sql, 'from "reservations"')) {
                $reservationSelects[] = $query->sql;
            }
        });

        $this->patchJson('/api/vendor/reservations/RES-NFPOA3R1/status', [
            'status' => 'confirmed',
            'vendorNote' => 'Table 4',
        ], $this->vendorHeaders())
            ->assertOk()
            ->assertJsonPath('status', 'confirmed')
            ->assertJsonPath('vendorNote', 'Table 4');

        $this->assertNotEmpty($reservationSelects);
        $this->assertStringNotContainsString('or "id"', $reservationSelects[0]);

        $this->patchJson('/api/vendor/reservations/RES-NFPOA3R1/status', [
            'status' => 'declined',
        ], $this->vendorHeaders())->assertUnprocessable();

        $this->patchJson('/api/vendor/reservations/RES-NFPOA3R1/status', [
            'status' => 'cancelled',
        ], $this->vendorHeaders())
            ->assertOk()
            ->assertJsonPath('status', 'cancelled');
    }

    public function test_vendor_can_update_a_reservation_by_numeric_id(): void
    {
        $reservation = $this->createReservation();

        $this->patchJson("/api/vendor/reservations/{$reservation->id}/status", [
            'status' => 'confirmed',
        ], $this->vendorHeaders())
            ->assertOk()
            ->assertJsonPath('id', (string) $reservation->id)
            ->assertJsonPath('status', 'confirmed');
    }

    public function test_vendor_cannot_read_another_vendors_reservations(): void
    {
        $otherVendor = Vendor::factory()->create();

        $this->getJson(
            "/api/vendor/{$otherVendor->id}/reservations",
            $this->vendorHeaders()
        )->assertForbidden();
    }

    public function test_reservation_filters_are_validated(): void
    {
        $this->getJson(
            "/api/vendor/{$this->vendor->id}/reservations?status=declined&date=20-08-2026",
            $this->vendorHeaders()
        )->assertUnprocessable();
    }

    private function createReservation(array $overrides = []): Reservation
    {
        return Reservation::create(array_merge([
            'reservation_public_id' => 'RES-NFPOA3R1',
            'vendor_id' => $this->vendor->id,
            'customer_id' => $this->customer->id,
            'guest_name' => 'Ada Lovelace',
            'guest_email' => 'ada@example.com',
            'guest_phone' => '+4312345678',
            'date' => '2026-08-20',
            'time' => '18:30',
            'party_size' => 4,
            'status' => 'pending',
            'customer_note' => 'Window seat, please.',
        ], $overrides));
    }

    private function vendorHeaders(): array
    {
        $token = $this->vendor->createToken('test')->plainTextToken;

        return [
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/json',
        ];
    }
}
