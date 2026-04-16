<?php

namespace Tests\Feature\Journey;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\JourneyRule;
use App\Models\OfflineSyncLog;
use App\Models\Tenant;
use App\Models\TimeClockEntry;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Gate::before(fn () => true);
    $this->withoutMiddleware([
        EnsureTenantScope::class,
        CheckPermission::class,
    ]);
    $this->tenant = Tenant::factory()->create();
    $this->user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'current_tenant_id' => $this->tenant->id,
        'is_active' => true,
    ]);
    app()->instance('current_tenant_id', $this->tenant->id);
    Sanctum::actingAs($this->user, ['*']);

    JourneyRule::factory()->default()->create([
        'tenant_id' => $this->tenant->id,
    ]);
});

it('accepts a batch of offline clock events', function () {
    $uuid1 = Str::uuid()->toString();
    $uuid2 = Str::uuid()->toString();

    $response = $this->postJson('/api/v1/journey/sync', [
        'events' => [
            [
                'event_type' => 'clock_in',
                '_offline_uuid' => $uuid1,
                '_local_timestamp' => '2026-04-09T08:00:00Z',
                'timestamp' => '2026-04-09T08:00:00Z',
                'latitude' => -23.5505,
                'longitude' => -46.6333,
            ],
            [
                'event_type' => 'clock_out',
                '_offline_uuid' => $uuid2,
                '_local_timestamp' => '2026-04-09T17:00:00Z',
                'timestamp' => '2026-04-09T17:00:00Z',
            ],
        ],
    ]);

    $response->assertOk()
        ->assertJsonPath('data.summary.total', 2)
        ->assertJsonPath('data.summary.accepted', 2)
        ->assertJsonPath('data.summary.duplicate', 0)
        ->assertJsonPath('data.summary.rejected', 0);

    expect(OfflineSyncLog::where('uuid', $uuid1)->exists())->toBeTrue();
    expect(OfflineSyncLog::where('uuid', $uuid2)->exists())->toBeTrue();
});

it('rejects duplicate UUID (idempotent)', function () {
    $uuid = Str::uuid()->toString();

    // First sync
    $this->postJson('/api/v1/journey/sync', [
        'events' => [[
            'event_type' => 'clock_in',
            '_offline_uuid' => $uuid,
            '_local_timestamp' => '2026-04-09T08:00:00Z',
        ]],
    ])->assertOk();

    // Second sync with same UUID
    $response = $this->postJson('/api/v1/journey/sync', [
        'events' => [[
            'event_type' => 'clock_in',
            '_offline_uuid' => $uuid,
            '_local_timestamp' => '2026-04-09T08:00:00Z',
        ]],
    ]);

    $response->assertOk()
        ->assertJsonPath('data.summary.duplicate', 1)
        ->assertJsonPath('data.summary.accepted', 0);
});

it('creates time clock entry from offline clock_in', function () {
    $uuid = Str::uuid()->toString();

    $this->postJson('/api/v1/journey/sync', [
        'events' => [[
            'event_type' => 'clock_in',
            '_offline_uuid' => $uuid,
            '_local_timestamp' => '2026-04-09T08:00:00Z',
            'timestamp' => '2026-04-09T08:00:00Z',
            'latitude' => -23.55,
            'longitude' => -46.63,
            'accuracy' => 15.0,
        ]],
    ])->assertOk();

    $entry = TimeClockEntry::where('user_id', $this->user->id)
        ->where('clock_method', 'offline')
        ->first();

    expect($entry)->not->toBeNull();
    expect((float) $entry->latitude_in)->toBe(-23.55);
});

it('processes clock_out matching open entry', function () {
    // Create open clock-in first
    TimeClockEntry::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'clock_in' => '2026-04-09 08:00:00',
        'clock_out' => null,
    ]);

    $uuid = Str::uuid()->toString();

    $this->postJson('/api/v1/journey/sync', [
        'events' => [[
            'event_type' => 'clock_out',
            '_offline_uuid' => $uuid,
            '_local_timestamp' => '2026-04-09T17:00:00Z',
            'timestamp' => '2026-04-09T17:00:00Z',
        ]],
    ])->assertOk();

    $entry = TimeClockEntry::where('user_id', $this->user->id)->first();
    expect($entry->clock_out)->not->toBeNull();
});

it('validates required fields', function () {
    $this->postJson('/api/v1/journey/sync', [
        'events' => [[
            'event_type' => 'clock_in',
            // missing _offline_uuid and _local_timestamp
        ]],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['events.0._offline_uuid', 'events.0._local_timestamp']);
});

it('validates event_type enum', function () {
    $this->postJson('/api/v1/journey/sync', [
        'events' => [[
            'event_type' => 'invalid_type',
            '_offline_uuid' => Str::uuid()->toString(),
            '_local_timestamp' => '2026-04-09T08:00:00Z',
        ]],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['events.0.event_type']);
});

it('processes events in local timestamp order', function () {
    $uuidOut = Str::uuid()->toString();
    $uuidIn = Str::uuid()->toString();

    // Send clock_out BEFORE clock_in but with correct timestamps
    $this->postJson('/api/v1/journey/sync', [
        'events' => [
            [
                'event_type' => 'clock_out',
                '_offline_uuid' => $uuidOut,
                '_local_timestamp' => '2026-04-09T17:00:00Z',
                'timestamp' => '2026-04-09T17:00:00Z',
            ],
            [
                'event_type' => 'clock_in',
                '_offline_uuid' => $uuidIn,
                '_local_timestamp' => '2026-04-09T08:00:00Z',
                'timestamp' => '2026-04-09T08:00:00Z',
            ],
        ],
    ])->assertOk()
        ->assertJsonPath('data.summary.accepted', 2);

    // clock_in should have been processed first due to timestamp ordering
    $logs = OfflineSyncLog::orderBy('local_timestamp')->get();
    expect($logs->first()->event_type)->toBe('clock_in');
    expect($logs->last()->event_type)->toBe('clock_out');
});

it('limits batch to 100 events', function () {
    $events = [];
    for ($i = 0; $i < 101; $i++) {
        $events[] = [
            'event_type' => 'clock_in',
            '_offline_uuid' => Str::uuid()->toString(),
            '_local_timestamp' => '2026-04-09T08:00:00Z',
        ];
    }

    $this->postJson('/api/v1/journey/sync', ['events' => $events])
        ->assertUnprocessable();
});
