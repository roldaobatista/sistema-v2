<?php

namespace Tests\Unit\Services;

use App\Models\Tenant;
use App\Models\TimeClockAuditLog;
use App\Models\TimeClockEntry;
use App\Models\User;
use Tests\TestCase;

class AuditTrailTest extends TestCase
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

    public function test_creating_clock_entry_generates_audit_log(): void
    {
        $entry = TimeClockEntry::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'type' => 'regular',
            'clock_method' => 'selfie',
            'ip_address' => '127.0.0.1',
        ]);

        $log = TimeClockAuditLog::where('time_clock_entry_id', $entry->id)
            ->where('action', 'created')
            ->first();

        $this->assertNotNull($log, 'Audit log should be created for new clock entry');
        $this->assertEquals($this->user->id, $log->performed_by);
        $this->assertIsArray($log->metadata);
        $this->assertArrayHasKey('clock_in', $log->metadata);
        $this->assertArrayHasKey('clock_method', $log->metadata);
    }

    public function test_approving_clock_entry_generates_audit_log(): void
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

        $log = TimeClockAuditLog::where('time_clock_entry_id', $entry->id)
            ->where('action', 'approved')
            ->first();

        $this->assertNotNull($log, 'Approval should be logged');
        $this->assertEquals('approved', $log->metadata['approval_status'] ?? null);
        $this->assertEquals($this->user->id, $log->metadata['approved_by'] ?? null);
    }

    public function test_rejecting_clock_entry_generates_audit_log(): void
    {
        $entry = TimeClockEntry::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'approval_status' => 'pending',
            'type' => 'regular',
            'clock_method' => 'selfie',
            'ip_address' => '127.0.0.1',
        ]);

        $entry->approval_status = 'rejected';
        $entry->rejection_reason = 'Invalid location';
        $entry->save();

        $log = TimeClockAuditLog::where('time_clock_entry_id', $entry->id)
            ->where('action', 'rejected')
            ->first();

        $this->assertNotNull($log, 'Rejection should be logged');
        $this->assertEquals('rejected', $log->metadata['approval_status'] ?? null);
    }

    public function test_clock_out_generates_audit_log(): void
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

        $entry->clock_out = now();
        $entry->save();

        $log = TimeClockAuditLog::where('time_clock_entry_id', $entry->id)
            ->where('action', 'clock_out')
            ->first();

        $this->assertNotNull($log, 'Clock-out should be logged');
        $this->assertArrayHasKey('clock_out', $log->metadata);
    }

    public function test_audit_log_static_log_method(): void
    {
        $log = TimeClockAuditLog::log('test_action', null, null, ['key' => 'value']);

        $this->assertNotNull($log);
        $this->assertEquals('test_action', $log->action);
        $this->assertEquals(['key' => 'value'], $log->metadata);
        $this->assertEquals($this->user->id, $log->performed_by);
        $this->assertEquals($this->tenant->id, $log->tenant_id);
    }

    public function test_audit_log_records_entry_id(): void
    {
        $entry = TimeClockEntry::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'type' => 'regular',
            'clock_method' => 'selfie',
            'ip_address' => '127.0.0.1',
        ]);

        $log = TimeClockAuditLog::log('custom_action', $entry->id, null, ['test' => true]);

        $this->assertEquals($entry->id, $log->time_clock_entry_id);
        $this->assertEquals('custom_action', $log->action);
    }

    public function test_audit_log_records_adjustment_id(): void
    {
        $log = TimeClockAuditLog::log('adjustment_action', null, 999, ['adjustment' => true]);

        $this->assertEquals(999, $log->time_clock_adjustment_id);
    }

    public function test_confirmation_generates_audit_log(): void
    {
        $entry = TimeClockEntry::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'confirmed_at' => null,
            'confirmation_method' => null,
            'type' => 'regular',
            'clock_method' => 'selfie',
            'ip_address' => '127.0.0.1',
        ]);

        $entry->confirmed_at = now();
        $entry->confirmation_method = 'pin';
        $entry->employee_confirmation_hash = hash('sha256', 'test');
        $entry->save();

        $log = TimeClockAuditLog::where('time_clock_entry_id', $entry->id)
            ->where('action', 'confirmed')
            ->first();

        $this->assertNotNull($log, 'Confirmation should be logged');
        $this->assertEquals('pin', $log->metadata['confirmation_method'] ?? null);
    }
}
