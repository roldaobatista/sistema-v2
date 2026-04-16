<?php

namespace Tests\Unit\Services;

use App\Models\Tenant;
use App\Models\TimeClockEntry;
use App\Models\User;
use App\Services\HashChainService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class HashChainServiceTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private HashChainService $service;

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

        $this->service = new HashChainService;
    }

    private function createEntry(string $clockIn, ?string $clockOut = null, array $extra = []): TimeClockEntry
    {
        return TimeClockEntry::factory()->create(array_merge([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'clock_in' => Carbon::parse($clockIn),
            'clock_out' => $clockOut ? Carbon::parse($clockOut) : null,
            'type' => 'regular',
            'approval_status' => 'auto_approved',
            'clock_method' => 'selfie',
            'ip_address' => '127.0.0.1',
            'latitude_in' => -23.5505199,
            'longitude_in' => -46.6333094,
        ], $extra));
    }

    public function test_apply_hash_sets_nsr_and_record_hash(): void
    {
        $entry = $this->createEntry('2026-03-18 08:00', '2026-03-18 17:00');

        $this->service->applyHash($entry);
        $entry->refresh();

        $this->assertNotNull($entry->nsr);
        $this->assertNotNull($entry->record_hash);
        $this->assertNotNull($entry->hash_payload);
        $this->assertGreaterThan(0, $entry->nsr);
        $this->assertEquals(64, strlen($entry->record_hash), 'SHA-256 hash should be 64 hex chars');
    }

    public function test_apply_hash_sets_previous_hash_to_null_for_first_entry(): void
    {
        $entry = $this->createEntry('2026-03-18 08:00', '2026-03-18 17:00');

        $this->service->applyHash($entry);
        $entry->refresh();

        $this->assertNull($entry->previous_hash);
    }

    public function test_apply_hash_chains_to_previous_entry(): void
    {
        $entry1 = $this->createEntry('2026-03-18 08:00', '2026-03-18 17:00');
        $this->service->applyHash($entry1);
        $entry1->refresh();

        $entry2 = $this->createEntry('2026-03-19 08:00', '2026-03-19 17:00');
        $this->service->applyHash($entry2);
        $entry2->refresh();

        $this->assertEquals($entry1->record_hash, $entry2->previous_hash);
    }

    public function test_nsr_is_sequential_per_tenant(): void
    {
        $entry1 = $this->createEntry('2026-03-18 08:00', '2026-03-18 17:00');
        $this->service->applyHash($entry1);
        $entry1->refresh();

        $entry2 = $this->createEntry('2026-03-19 08:00', '2026-03-19 17:00');
        $this->service->applyHash($entry2);
        $entry2->refresh();

        $this->assertEquals($entry1->nsr + 1, $entry2->nsr);
    }

    public function test_nsr_is_independent_per_tenant(): void
    {
        $entry1 = $this->createEntry('2026-03-18 08:00', '2026-03-18 17:00');
        $this->service->applyHash($entry1);
        $entry1->refresh();

        // Create second tenant with its own user and entry
        $tenant2 = Tenant::factory()->create();
        $user2 = User::factory()->create([
            'tenant_id' => $tenant2->id,
            'current_tenant_id' => $tenant2->id,
        ]);
        $user2->tenants()->attach($tenant2->id, ['is_default' => true]);

        $entry2 = TimeClockEntry::factory()->create([
            'tenant_id' => $tenant2->id,
            'user_id' => $user2->id,
            'clock_in' => Carbon::parse('2026-03-18 08:00'),
            'clock_out' => Carbon::parse('2026-03-18 17:00'),
            'type' => 'regular',
            'approval_status' => 'auto_approved',
            'clock_method' => 'selfie',
            'ip_address' => '127.0.0.1',
        ]);
        $this->service->applyHash($entry2);
        $entry2->refresh();

        // Both should start from NSR 1
        $this->assertEquals(1, $entry1->nsr);
        $this->assertEquals(1, $entry2->nsr);
    }

    public function test_hash_changes_when_clock_out_added(): void
    {
        $entry = $this->createEntry('2026-03-18 08:00');
        $this->service->applyHash($entry);
        $entry->refresh();
        $originalHash = $entry->record_hash;

        // Simulate clock-out
        $entry->clock_out = Carbon::parse('2026-03-18 17:00');
        $entry->saveQuietly();
        $this->service->reapplyHash($entry);
        $entry->refresh();

        $this->assertNotEquals($originalHash, $entry->record_hash);
    }

    public function test_verify_entry_valid_hash(): void
    {
        $entry = $this->createEntry('2026-03-18 08:00', '2026-03-18 17:00');
        $this->service->applyHash($entry);
        $entry->refresh();

        $result = $this->service->verifyEntry($entry);

        $this->assertTrue($result['is_valid']);
        $this->assertEquals($entry->record_hash, $result['actual_hash']);
    }

    public function test_verify_entry_detects_tampered_record(): void
    {
        $entry = $this->createEntry('2026-03-18 08:00', '2026-03-18 17:00');
        $this->service->applyHash($entry);
        $entry->refresh();

        // Tamper with the hash
        TimeClockEntry::withoutGlobalScopes()
            ->where('id', $entry->id)
            ->update(['record_hash' => 'tampered_hash_value']);
        $entry->refresh();

        $result = $this->service->verifyEntry($entry);

        $this->assertFalse($result['is_valid']);
    }

    public function test_verify_chain_valid(): void
    {
        $entry1 = $this->createEntry('2026-03-18 08:00', '2026-03-18 17:00');
        $this->service->applyHash($entry1);

        $entry2 = $this->createEntry('2026-03-19 08:00', '2026-03-19 17:00');
        $this->service->applyHash($entry2);

        $result = $this->service->verifyChain(
            $this->tenant->id,
            Carbon::parse('2026-03-18'),
            Carbon::parse('2026-03-19')
        );

        $this->assertTrue($result['is_valid']);
        $this->assertEquals(2, $result['total_records']);
        $this->assertEquals(0, $result['invalid_count']);
        $this->assertEmpty($result['nsr_gaps']);
    }

    public function test_verify_chain_detects_broken_link(): void
    {
        $entry1 = $this->createEntry('2026-03-18 08:00', '2026-03-18 17:00');
        $this->service->applyHash($entry1);

        $entry2 = $this->createEntry('2026-03-19 08:00', '2026-03-19 17:00');
        $this->service->applyHash($entry2);
        $entry2->refresh();

        // Tamper with entry2's previous_hash to break the chain link
        TimeClockEntry::withoutGlobalScopes()
            ->where('id', $entry2->id)
            ->update(['previous_hash' => 'broken_link']);
        $entry2->refresh();

        $result = $this->service->verifyChain(
            $this->tenant->id,
            Carbon::parse('2026-03-18'),
            Carbon::parse('2026-03-19')
        );

        $this->assertFalse($result['is_valid']);
        $this->assertGreaterThan(0, $result['invalid_count']);
    }

    public function test_verify_chain_detects_nsr_gaps(): void
    {
        $entry1 = $this->createEntry('2026-03-18 08:00', '2026-03-18 17:00');
        $this->service->applyHash($entry1);
        $entry1->refresh();

        $entry2 = $this->createEntry('2026-03-19 08:00', '2026-03-19 17:00');
        $this->service->applyHash($entry2);
        $entry2->refresh();

        // Create a gap by changing entry2's NSR from 2 to 4
        TimeClockEntry::withoutGlobalScopes()
            ->where('id', $entry2->id)
            ->update(['nsr' => 4]);

        $result = $this->service->verifyChain(
            $this->tenant->id,
            Carbon::parse('2026-03-18'),
            Carbon::parse('2026-03-19')
        );

        $this->assertFalse($result['is_valid']);
        $this->assertNotEmpty($result['nsr_gaps']);
        $this->assertContains(2, $result['nsr_gaps']);
        $this->assertContains(3, $result['nsr_gaps']);
    }

    public function test_hash_payload_contains_all_required_fields(): void
    {
        $entry = $this->createEntry('2026-03-18 08:00', '2026-03-18 17:00');
        $this->service->applyHash($entry);
        $entry->refresh();

        $payload = json_decode($entry->hash_payload, true);

        $this->assertArrayHasKey('user_id', $payload);
        $this->assertArrayHasKey('clock_in', $payload);
        $this->assertArrayHasKey('clock_out', $payload);
        $this->assertArrayHasKey('latitude_in', $payload);
        $this->assertArrayHasKey('longitude_in', $payload);
        $this->assertArrayHasKey('clock_method', $payload);
        $this->assertArrayHasKey('device_info', $payload);
        $this->assertArrayHasKey('ip_address', $payload);
        $this->assertEquals($this->user->id, $payload['user_id']);
    }

    public function test_reapply_hash_preserves_nsr_and_previous_hash(): void
    {
        $entry = $this->createEntry('2026-03-18 08:00');
        $this->service->applyHash($entry);
        $entry->refresh();
        $originalNsr = $entry->nsr;
        $originalPreviousHash = $entry->previous_hash;

        $entry->clock_out = Carbon::parse('2026-03-18 17:00');
        $entry->saveQuietly();
        $this->service->reapplyHash($entry);
        $entry->refresh();

        // NSR and previous_hash should NOT change on reapply
        $this->assertEquals($originalNsr, $entry->nsr);
        $this->assertEquals($originalPreviousHash, $entry->previous_hash);
    }

    public function test_generate_hash_is_deterministic(): void
    {
        $entry = $this->createEntry('2026-03-18 08:00', '2026-03-18 17:00');
        $this->service->applyHash($entry);
        $entry->refresh();

        $hash1 = $this->service->generateHash($entry);
        $hash2 = $this->service->generateHash($entry);

        $this->assertEquals($hash1, $hash2);
    }
}
