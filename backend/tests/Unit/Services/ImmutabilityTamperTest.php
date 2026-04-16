<?php

namespace Tests\Unit\Services;

use App\Models\Tenant;
use App\Models\TimeClockAuditLog;
use App\Models\TimeClockEntry;
use App\Models\User;
use Tests\TestCase;

class ImmutabilityTamperTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);

        app()->instance('current_tenant_id', $this->tenant->id);
        $this->actingAs($this->user);
    }

    public function test_tampering_clock_in_is_logged_and_blocked(): void
    {
        $entry = TimeClockEntry::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'clock_in' => now()->subHours(2),
            'type' => 'regular',
            'clock_method' => 'selfie',
            'ip_address' => '127.0.0.1',
        ]);

        $caughtException = false;

        try {
            $entry->clock_in = now();
            $entry->save();
        } catch (\DomainException $e) {
            $caughtException = true;
        }

        $this->assertTrue($caughtException, 'DomainException should be thrown for immutable field change');

        $tamperLog = TimeClockAuditLog::where('time_clock_entry_id', $entry->id)
            ->where('action', 'tampering_attempt')
            ->first();

        $this->assertNotNull($tamperLog, 'Tampering attempt should be logged');
        $this->assertEquals('clock_in', $tamperLog->metadata['field'] ?? null);
    }

    public function test_immutable_fields_cannot_be_changed(): void
    {
        $entry = TimeClockEntry::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'latitude_in' => -23.5505,
            'type' => 'regular',
            'clock_method' => 'selfie',
            'ip_address' => '127.0.0.1',
        ]);

        $this->expectException(\DomainException::class);

        $entry->latitude_in = -22.0000;
        $entry->save();
    }

    public function test_longitude_in_is_immutable(): void
    {
        $entry = TimeClockEntry::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'longitude_in' => -46.6333,
            'type' => 'regular',
            'clock_method' => 'selfie',
            'ip_address' => '127.0.0.1',
        ]);

        $this->expectException(\DomainException::class);

        $entry->longitude_in = -45.0000;
        $entry->save();
    }

    public function test_clock_method_is_immutable(): void
    {
        $entry = TimeClockEntry::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'clock_method' => 'selfie',
            'type' => 'regular',
            'ip_address' => '127.0.0.1',
        ]);

        $this->expectException(\DomainException::class);

        $entry->clock_method = 'manual';
        $entry->save();
    }

    public function test_mutable_fields_can_be_updated(): void
    {
        $entry = TimeClockEntry::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'approval_status' => 'pending',
            'type' => 'regular',
            'clock_method' => 'selfie',
            'ip_address' => '127.0.0.1',
        ]);

        $entry->approval_status = 'approved';
        $entry->approved_by = $this->user->id;
        $entry->save();

        $this->assertEquals('approved', $entry->fresh()->approval_status);
        $this->assertEquals($this->user->id, $entry->fresh()->approved_by);
    }

    public function test_notes_can_be_updated(): void
    {
        $entry = TimeClockEntry::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'notes' => 'original note',
            'type' => 'regular',
            'clock_method' => 'selfie',
            'ip_address' => '127.0.0.1',
        ]);

        $entry->notes = 'updated note';
        $entry->save();

        $this->assertEquals('updated note', $entry->fresh()->notes);
    }

    public function test_clock_out_can_be_set_when_null(): void
    {
        $entry = TimeClockEntry::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'clock_in' => now()->subHours(8),
            'clock_out' => null,
            'type' => 'regular',
            'clock_method' => 'selfie',
            'ip_address' => '127.0.0.1',
        ]);

        // Setting clock_out for the first time (null -> value) should be allowed
        $entry->clock_out = now();
        $entry->save();

        $this->assertNotNull($entry->fresh()->clock_out);
    }

    public function test_clock_out_cannot_be_changed_once_set(): void
    {
        $clockOut = now()->subHour();

        $entry = TimeClockEntry::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'clock_in' => now()->subHours(8),
            'clock_out' => $clockOut,
            'type' => 'regular',
            'clock_method' => 'selfie',
            'ip_address' => '127.0.0.1',
        ]);

        $this->expectException(\DomainException::class);

        $entry->clock_out = now();
        $entry->save();
    }
}
