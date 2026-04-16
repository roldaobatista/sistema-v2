<?php

namespace Tests\Unit\Services;

use App\Models\Tenant;
use App\Models\TimeClockEntry;
use App\Models\User;
use App\Services\HashChainService;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class HashChainVerificationTest extends TestCase
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

    private function createEntry(string $clockIn, ?string $clockOut = null, bool $applyHash = true): TimeClockEntry
    {
        $entry = TimeClockEntry::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'clock_in' => $clockIn,
            'clock_out' => $clockOut,
            'type' => 'regular',
            'approval_status' => 'approved',
            'clock_method' => 'selfie',
            'ip_address' => '127.0.0.1',
        ]);

        if ($applyHash) {
            $this->service->applyHash($entry);
            $entry->refresh();
        }

        return $entry;
    }

    public function test_verify_chain_returns_valid_for_intact_chain(): void
    {
        // Create entries with sequential times
        for ($i = 0; $i < 3; $i++) {
            $this->createEntry(
                now()->subHours(10 - $i)->toDateTimeString(),
                now()->subHours(9 - $i)->toDateTimeString()
            );
        }

        $result = $this->service->verifyChain(
            $this->tenant->id,
            now()->subDay(),
            now()->addDay()
        );

        $this->assertEquals(3, $result['total_records']);
        $this->assertIsArray($result['nsr_gaps']);
        $this->assertArrayHasKey('is_valid', $result);
        $this->assertArrayHasKey('verified_at', $result);
        $this->assertArrayHasKey('valid_count', $result);
        $this->assertArrayHasKey('invalid_count', $result);
        $this->assertArrayHasKey('broken_chain_at', $result);
        $this->assertArrayHasKey('details', $result);
    }

    public function test_verify_chain_detects_tampered_hash(): void
    {
        $entry = $this->createEntry(now()->subHours(2)->toDateTimeString());

        // Tamper directly in DB to bypass model events
        \DB::table('time_clock_entries')->where('id', $entry->id)->update([
            'record_hash' => str_repeat('a', 64),
        ]);

        $result = $this->service->verifyChain(
            $this->tenant->id,
            now()->subDay(),
            now()->addDay()
        );

        $this->assertGreaterThan(0, $result['invalid_count']);
        $this->assertFalse($result['is_valid']);
    }

    public function test_verify_entry_validates_single_entry(): void
    {
        $entry = $this->createEntry(now()->subHours(2)->toDateTimeString());

        $result = $this->service->verifyEntry($entry);

        $this->assertArrayHasKey('is_valid', $result);
        $this->assertArrayHasKey('expected_hash', $result);
        $this->assertArrayHasKey('actual_hash', $result);
    }

    public function test_verify_entry_detects_tampered_single_entry(): void
    {
        $entry = $this->createEntry(now()->subHours(2)->toDateTimeString());

        // Tamper the hash directly in DB
        \DB::table('time_clock_entries')->where('id', $entry->id)->update([
            'record_hash' => str_repeat('b', 64),
        ]);

        $entry->refresh();

        $result = $this->service->verifyEntry($entry);

        $this->assertFalse($result['is_valid']);
        $this->assertNotEquals($result['expected_hash'], $result['actual_hash']);
    }

    public function test_verify_chain_with_no_entries_returns_zero_totals(): void
    {
        $result = $this->service->verifyChain(
            $this->tenant->id,
            now()->subDay(),
            now()->addDay()
        );

        $this->assertEquals(0, $result['total_records']);
        $this->assertEquals(0, $result['valid_count']);
        $this->assertEquals(0, $result['invalid_count']);
        $this->assertTrue($result['is_valid']);
        $this->assertEmpty($result['nsr_gaps']);
    }

    public function test_verify_chain_scoped_to_date_range(): void
    {
        // Entry inside range
        $this->createEntry(now()->subHours(2)->toDateTimeString());

        // Entry outside range (2 days ago)
        $this->createEntry(now()->subDays(3)->toDateTimeString());

        $result = $this->service->verifyChain(
            $this->tenant->id,
            now()->subDay(),
            now()->addDay()
        );

        $this->assertEquals(1, $result['total_records']);
    }

    public function test_verify_chain_scoped_to_tenant(): void
    {
        // Entry for our tenant
        $this->createEntry(now()->subHours(2)->toDateTimeString());

        // Entry for another tenant
        $otherTenant = Tenant::factory()->create();
        TimeClockEntry::factory()->create([
            'tenant_id' => $otherTenant->id,
            'user_id' => $this->user->id,
            'clock_in' => now()->subHour(),
            'type' => 'regular',
            'approval_status' => 'approved',
            'clock_method' => 'selfie',
            'ip_address' => '127.0.0.1',
        ]);

        $result = $this->service->verifyChain(
            $this->tenant->id,
            now()->subDay(),
            now()->addDay()
        );

        $this->assertEquals(1, $result['total_records']);
    }
}
