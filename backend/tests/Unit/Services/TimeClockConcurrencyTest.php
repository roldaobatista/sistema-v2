<?php

namespace Tests\Unit\Services;

use App\Models\Tenant;
use App\Models\TimeClockEntry;
use App\Models\User;
use App\Services\HashChainService;
use App\Services\LocationValidationService;
use App\Services\TimeClockService;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class TimeClockConcurrencyTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private TimeClockService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();

        $this->tenant = Tenant::factory()->create([
            'timezone' => 'America/Sao_Paulo',
        ]);
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);

        app()->instance('current_tenant_id', $this->tenant->id);
        $this->actingAs($this->user);

        $this->service = new TimeClockService(
            new HashChainService,
            new LocationValidationService,
        );
    }

    private function clockInData(array $overrides = []): array
    {
        return array_merge([
            'latitude' => -23.5505199,
            'longitude' => -46.6333094,
            'accuracy' => 10,
            'liveness_score' => 0.95,
            'clock_method' => 'selfie',
            'device_info' => 'Test Device',
            'ip_address' => '127.0.0.1',
        ], $overrides);
    }

    // ═══════════════════════════════════════════════════════════════
    // Duplicate clock-in prevention
    // ═══════════════════════════════════════════════════════════════

    public function test_clock_in_throws_when_open_entry_exists(): void
    {
        // First clock-in should succeed
        $entry = $this->service->clockIn($this->user, $this->clockInData());
        $this->assertNotNull($entry);
        $this->assertNull($entry->clock_out);

        // Second clock-in should throw
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Já existe um ponto aberto');

        $this->service->clockIn($this->user, $this->clockInData());
    }

    public function test_clock_in_succeeds_after_clock_out(): void
    {
        // Clock in and out
        $entry1 = $this->service->clockIn($this->user, $this->clockInData());
        $this->service->clockOut($this->user, $this->clockInData());

        // Should succeed now
        $entry2 = $this->service->clockIn($this->user, $this->clockInData());
        $this->assertNotNull($entry2);
        $this->assertNotEquals($entry1->id, $entry2->id);
    }

    // ═══════════════════════════════════════════════════════════════
    // Clock-out validation
    // ═══════════════════════════════════════════════════════════════

    public function test_clock_out_throws_when_no_open_entry(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Nenhum ponto aberto encontrado');

        $this->service->clockOut($this->user, $this->clockInData());
    }

    public function test_clock_out_closes_the_open_entry(): void
    {
        $entry = $this->service->clockIn($this->user, $this->clockInData());
        $this->assertNull($entry->clock_out);

        $closedEntry = $this->service->clockOut($this->user, [
            'latitude' => -23.5505199,
            'longitude' => -46.6333094,
            'accuracy' => 10,
        ]);

        $this->assertNotNull($closedEntry->clock_out);
        $this->assertEquals($entry->id, $closedEntry->id);
    }

    public function test_double_clock_out_throws(): void
    {
        $this->service->clockIn($this->user, $this->clockInData());
        $this->service->clockOut($this->user, $this->clockInData());

        // Second clock-out should throw since entry is already closed
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Nenhum ponto aberto encontrado');

        $this->service->clockOut($this->user, $this->clockInData());
    }

    // ═══════════════════════════════════════════════════════════════
    // Multi-user isolation
    // ═══════════════════════════════════════════════════════════════

    public function test_different_users_can_clock_in_independently(): void
    {
        $user2 = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $user2->tenants()->attach($this->tenant->id, ['is_default' => true]);

        // Both users clock in
        $entry1 = $this->service->clockIn($this->user, $this->clockInData());
        $entry2 = $this->service->clockIn($user2, $this->clockInData());

        $this->assertNotNull($entry1);
        $this->assertNotNull($entry2);
        $this->assertNotEquals($entry1->id, $entry2->id);
        $this->assertEquals($this->user->id, $entry1->user_id);
        $this->assertEquals($user2->id, $entry2->user_id);
    }

    public function test_user_clock_out_does_not_affect_other_user(): void
    {
        $user2 = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $user2->tenants()->attach($this->tenant->id, ['is_default' => true]);

        $this->service->clockIn($this->user, $this->clockInData());
        $this->service->clockIn($user2, $this->clockInData());

        // Only user1 clocks out
        $this->service->clockOut($this->user, $this->clockInData());

        // user2 should still have open entry
        $openEntry = TimeClockEntry::where('user_id', $user2->id)
            ->whereNull('clock_out')
            ->first();
        $this->assertNotNull($openEntry);
    }

    // ═══════════════════════════════════════════════════════════════
    // Auto clock-in from OS (work order)
    // ═══════════════════════════════════════════════════════════════

    public function test_auto_clock_in_returns_null_when_already_clocked_in(): void
    {
        $this->service->clockIn($this->user, $this->clockInData());

        // Auto clock-in should return null (already clocked in)
        $result = $this->service->autoClockInFromOS($this->user, 123, [
            'latitude' => -23.5505199,
            'longitude' => -46.6333094,
        ]);

        $this->assertNull($result);
    }

    public function test_auto_clock_in_succeeds_when_no_open_entry(): void
    {
        $result = $this->service->autoClockInFromOS($this->user, 456, [
            'latitude' => -23.5505199,
            'longitude' => -46.6333094,
        ]);

        $this->assertNotNull($result);
        $this->assertEquals('auto_os', $result->clock_method);
        $this->assertEquals(456, $result->work_order_id);
    }

    // ═══════════════════════════════════════════════════════════════
    // Liveness and approval status
    // ═══════════════════════════════════════════════════════════════

    public function test_low_liveness_score_sets_pending_approval(): void
    {
        $entry = $this->service->clockIn($this->user, $this->clockInData([
            'liveness_score' => 0.5, // Below 0.8 threshold
        ]));

        $this->assertEquals('pending', $entry->approval_status);
        $this->assertFalse((bool) $entry->liveness_passed);
    }

    public function test_high_liveness_score_sets_auto_approved(): void
    {
        $entry = $this->service->clockIn($this->user, $this->clockInData([
            'liveness_score' => 0.95,
        ]));

        $this->assertEquals('auto_approved', $entry->approval_status);
        $this->assertTrue((bool) $entry->liveness_passed);
    }

    // ═══════════════════════════════════════════════════════════════
    // Hash chain integrity after operations
    // ═══════════════════════════════════════════════════════════════

    public function test_clock_in_applies_hash_chain(): void
    {
        $entry = $this->service->clockIn($this->user, $this->clockInData());

        $this->assertNotNull($entry->record_hash);
        $this->assertNotNull($entry->nsr);
        $this->assertEquals(64, strlen($entry->record_hash));
    }

    public function test_clock_out_reapplies_hash(): void
    {
        $entry = $this->service->clockIn($this->user, $this->clockInData());
        $hashAfterClockIn = $entry->record_hash;

        $closedEntry = $this->service->clockOut($this->user, $this->clockInData());

        // Hash should be different after clock_out (payload changed)
        $this->assertNotEquals($hashAfterClockIn, $closedEntry->record_hash);
    }

    // ═══════════════════════════════════════════════════════════════
    // Location spoofing detection
    // ═══════════════════════════════════════════════════════════════

    public function test_high_accuracy_value_flags_spoofing(): void
    {
        $entry = $this->service->clockIn($this->user, $this->clockInData([
            'accuracy' => 200, // > 150m threshold
        ]));

        $this->assertTrue((bool) $entry->location_spoofing_detected);
    }

    public function test_normal_accuracy_does_not_flag_spoofing(): void
    {
        $entry = $this->service->clockIn($this->user, $this->clockInData([
            'accuracy' => 10,
        ]));

        $this->assertFalse((bool) $entry->location_spoofing_detected);
    }
}
