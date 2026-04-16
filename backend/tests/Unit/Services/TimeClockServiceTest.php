<?php

namespace Tests\Unit\Services;

use App\Models\GeofenceLocation;
use App\Models\Tenant;
use App\Models\TimeClockAdjustment;
use App\Models\TimeClockEntry;
use App\Models\User;
use App\Services\TimeClockService;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class TimeClockServiceTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private TimeClockService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);

        app()->instance('current_tenant_id', $this->tenant->id);
        $this->actingAs($this->user);

        $this->service = app(TimeClockService::class);
    }

    public function test_clock_in_creates_entry_with_hash(): void
    {
        $entry = $this->service->clockIn($this->user, [
            'type' => 'regular',
            'clock_method' => 'selfie',
            'liveness_score' => 0.95,
        ]);

        $this->assertInstanceOf(TimeClockEntry::class, $entry);
        $this->assertEquals($this->user->id, $entry->user_id);
        $this->assertEquals($this->tenant->id, $entry->tenant_id);
        $this->assertNotNull($entry->clock_in);
        $this->assertNull($entry->clock_out);
        $this->assertNotNull($entry->record_hash, 'Entry should have a record_hash after clock-in');
        $this->assertNotNull($entry->nsr, 'Entry should have an NSR number');
        $this->assertEquals('auto_approved', $entry->approval_status);
    }

    public function test_clock_in_prevents_duplicate(): void
    {
        // First clock-in should succeed
        $this->service->clockIn($this->user, [
            'type' => 'regular',
            'clock_method' => 'selfie',
            'liveness_score' => 0.95,
        ]);

        // Second clock-in should throw exception
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Já existe um ponto aberto');

        $this->service->clockIn($this->user, [
            'type' => 'regular',
            'clock_method' => 'selfie',
            'liveness_score' => 0.95,
        ]);
    }

    public function test_clock_out_updates_entry(): void
    {
        $entry = $this->service->clockIn($this->user, [
            'type' => 'regular',
            'clock_method' => 'selfie',
            'liveness_score' => 0.95,
        ]);

        $this->assertNull($entry->clock_out);

        $updatedEntry = $this->service->clockOut($this->user, []);

        $this->assertNotNull($updatedEntry->clock_out);
        $this->assertEquals($entry->id, $updatedEntry->id);
    }

    public function test_clock_in_flags_entry_when_outside_geofence(): void
    {
        // Create a geofence location at lat=-23.55, lng=-46.63 with 100m radius
        $geofence = GeofenceLocation::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Office',
            'latitude' => -23.550000,
            'longitude' => -46.630000,
            'radius_meters' => 100,
            'is_active' => true,
        ]);

        // Clock in from a location far outside the geofence
        $entry = $this->service->clockIn($this->user, [
            'type' => 'regular',
            'clock_method' => 'selfie',
            'liveness_score' => 0.95,
            'latitude' => -23.600000, // Far from geofence center
            'longitude' => -46.700000,
            'geofence_location_id' => $geofence->id,
        ]);

        $this->assertEquals('pending', $entry->approval_status, 'Entry outside geofence should be pending approval');
    }

    public function test_clock_in_flags_entry_when_liveness_fails(): void
    {
        $entry = $this->service->clockIn($this->user, [
            'type' => 'regular',
            'clock_method' => 'selfie',
            'liveness_score' => 0.3, // Below 0.8 threshold
        ]);

        $this->assertFalse($entry->liveness_passed);
        $this->assertEquals('pending', $entry->approval_status, 'Entry with failed liveness should be pending approval');
    }

    public function test_approve_clock_entry(): void
    {
        // Create entry with pending status (liveness fail)
        $entry = $this->service->clockIn($this->user, [
            'type' => 'regular',
            'clock_method' => 'selfie',
            'liveness_score' => 0.3,
        ]);
        $this->assertEquals('pending', $entry->approval_status);

        $approver = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);

        $approved = $this->service->approveClockEntry($entry->id, $approver);

        $this->assertEquals('approved', $approved->approval_status);
        $this->assertEquals($approver->id, $approved->approved_by);
    }

    public function test_reject_clock_entry(): void
    {
        $entry = $this->service->clockIn($this->user, [
            'type' => 'regular',
            'clock_method' => 'selfie',
            'liveness_score' => 0.3,
        ]);
        $this->assertEquals('pending', $entry->approval_status);

        $approver = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);

        $rejected = $this->service->rejectClockEntry($entry->id, $approver, 'Selfie suspeita');

        $this->assertEquals('rejected', $rejected->approval_status);
        $this->assertEquals($approver->id, $rejected->approved_by);
        $this->assertEquals('Selfie suspeita', $rejected->rejection_reason);
    }

    public function test_request_adjustment_creates_record(): void
    {
        $entry = $this->service->clockIn($this->user, [
            'type' => 'regular',
            'clock_method' => 'selfie',
            'liveness_score' => 0.95,
        ]);

        // Clock out so the entry is complete
        $this->service->clockOut($this->user, []);

        $adjustment = $this->service->requestAdjustment($this->user, $entry->id, [
            'adjusted_clock_in' => now()->subHours(9),
            'adjusted_clock_out' => now()->subMinutes(30),
            'reason' => 'Esqueci de bater o ponto na hora correta',
        ]);

        $this->assertInstanceOf(TimeClockAdjustment::class, $adjustment);
        $this->assertEquals('pending', $adjustment->status);
        $this->assertEquals($this->user->id, $adjustment->requested_by);
        $this->assertEquals($entry->id, $adjustment->time_clock_entry_id);
        $this->assertEquals($entry->clock_in, $adjustment->original_clock_in);
        $this->assertEquals('Esqueci de bater o ponto na hora correta', $adjustment->reason);
    }

    public function test_approve_adjustment_updates_entry(): void
    {
        $entry = $this->service->clockIn($this->user, [
            'type' => 'regular',
            'clock_method' => 'selfie',
            'liveness_score' => 0.95,
        ]);

        $this->service->clockOut($this->user, []);
        $entry->refresh();

        $newClockIn = now()->subHours(9);
        $newClockOut = now()->subMinutes(30);

        $adjustment = $this->service->requestAdjustment($this->user, $entry->id, [
            'adjusted_clock_in' => $newClockIn,
            'adjusted_clock_out' => $newClockOut,
            'reason' => 'Hora incorreta',
        ]);

        $approver = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);

        $approvedAdjustment = $this->service->approveAdjustment($adjustment->id, $approver);

        $this->assertEquals('approved', $approvedAdjustment->status);
        $this->assertEquals($approver->id, $approvedAdjustment->approved_by);
        $this->assertNotNull($approvedAdjustment->decided_at);

        // Verify the original entry was updated
        $entry->refresh();
        $this->assertEquals(
            $newClockIn->format('Y-m-d H:i'),
            $entry->clock_in->format('Y-m-d H:i'),
            'Clock-in should be updated after approved adjustment'
        );
        $this->assertEquals(
            $newClockOut->format('Y-m-d H:i'),
            $entry->clock_out->format('Y-m-d H:i'),
            'Clock-out should be updated after approved adjustment'
        );
    }
}
