<?php

namespace Tests\Unit\Models;

use App\Models\InmetroCompetitor;
use App\Models\InmetroHistory;
use App\Models\InmetroInstrument;
use App\Models\InmetroLocation;
use App\Models\InmetroOwner;
use App\Models\InmetroSnapshot;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class InmetroDeepTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

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
    }

    // ── InmetroOwner ──

    public function test_owner_creation(): void
    {
        $o = InmetroOwner::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->assertNotNull($o);
    }

    public function test_owner_has_instruments(): void
    {
        $o = InmetroOwner::factory()->create(['tenant_id' => $this->tenant->id]);
        $location = InmetroLocation::factory()->create(['owner_id' => $o->id]);
        InmetroInstrument::factory()->count(5)->create([
            'tenant_id' => $this->tenant->id,
            'location_id' => $location->id,
        ]);
        $this->assertGreaterThanOrEqual(5, $o->instruments()->count());
    }

    public function test_owner_state_code(): void
    {
        $o = InmetroOwner::factory()->create([
            'tenant_id' => $this->tenant->id,
            'state' => 'SP',
        ]);
        $this->assertEquals('SP', $o->state);
    }

    public function test_owner_soft_deletes(): void
    {
        $o = InmetroOwner::factory()->create(['tenant_id' => $this->tenant->id]);
        $o->delete();
        $this->assertNotNull(InmetroOwner::withTrashed()->find($o->id));
    }

    // ── InmetroInstrument ──

    public function test_instrument_belongs_to_owner(): void
    {
        $o = InmetroOwner::factory()->create(['tenant_id' => $this->tenant->id]);
        $location = InmetroLocation::factory()->create(['owner_id' => $o->id]);
        $i = InmetroInstrument::factory()->create([
            'tenant_id' => $this->tenant->id,
            'location_id' => $location->id,
        ]);
        $this->assertInstanceOf(InmetroOwner::class, $i->owner);
    }

    public function test_instrument_type(): void
    {
        $o = InmetroOwner::factory()->create(['tenant_id' => $this->tenant->id]);
        $location = InmetroLocation::factory()->create(['owner_id' => $o->id]);
        $i = InmetroInstrument::factory()->create([
            'tenant_id' => $this->tenant->id,
            'location_id' => $location->id,
            'instrument_type' => 'balanca',
        ]);
        $this->assertEquals('balanca', $i->instrument_type);
    }

    public function test_instrument_capacity(): void
    {
        $o = InmetroOwner::factory()->create(['tenant_id' => $this->tenant->id]);
        $location = InmetroLocation::factory()->create(['owner_id' => $o->id]);
        $i = InmetroInstrument::factory()->create([
            'tenant_id' => $this->tenant->id,
            'location_id' => $location->id,
            'capacity' => '30000',
        ]);
        $this->assertEquals('30000', $i->capacity);
    }

    public function test_instrument_owner_relation_survives_refresh(): void
    {
        $o = InmetroOwner::factory()->create(['tenant_id' => $this->tenant->id]);
        $location = InmetroLocation::factory()->create(['owner_id' => $o->id]);
        $i = InmetroInstrument::factory()->create([
            'tenant_id' => $this->tenant->id,
            'location_id' => $location->id,
        ]);
        $this->assertEquals($o->id, $i->fresh()->owner?->id);
    }

    // ── InmetroCompetitor ──

    public function test_competitor_creation(): void
    {
        $c = InmetroCompetitor::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->assertNotNull($c);
    }

    public function test_competitor_has_instruments(): void
    {
        $c = InmetroCompetitor::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->assertInstanceOf(BelongsToMany::class, $c->instruments());
    }

    // ── InmetroSnapshot ──

    public function test_snapshot_creation(): void
    {
        $s = InmetroSnapshot::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->assertNotNull($s);
    }

    public function test_snapshot_data_is_json(): void
    {
        $s = InmetroSnapshot::factory()->create([
            'tenant_id' => $this->tenant->id,
            'data' => ['total_instruments' => 500, 'repair_count' => 100],
        ]);
        $s->refresh();
        $this->assertIsArray($s->data);
    }

    // ── InmetroHistory ──

    public function test_history_creation(): void
    {
        $o = InmetroOwner::factory()->create(['tenant_id' => $this->tenant->id]);
        $location = InmetroLocation::factory()->create(['owner_id' => $o->id]);
        $i = InmetroInstrument::factory()->create([
            'tenant_id' => $this->tenant->id,
            'location_id' => $location->id,
        ]);
        $insp = InmetroHistory::query()->create([
            'instrument_id' => $i->id,
            'event_type' => 'verification',
            'event_date' => now()->toDateString(),
            'result' => 'approved',
        ]);
        $this->assertEquals('approved', $insp->result);
    }

    public function test_history_rejected(): void
    {
        $o = InmetroOwner::factory()->create(['tenant_id' => $this->tenant->id]);
        $location = InmetroLocation::factory()->create(['owner_id' => $o->id]);
        $i = InmetroInstrument::factory()->create([
            'tenant_id' => $this->tenant->id,
            'location_id' => $location->id,
        ]);
        $insp = InmetroHistory::query()->create([
            'instrument_id' => $i->id,
            'event_type' => 'rejection',
            'event_date' => now()->toDateString(),
            'result' => 'rejected',
        ]);
        $this->assertEquals('rejected', $insp->result);
    }

    // ── Scopes ──

    public function test_owner_scope_by_state(): void
    {
        InmetroOwner::factory()->create(['tenant_id' => $this->tenant->id, 'state' => 'RJ']);
        $results = InmetroOwner::where('state', 'RJ')->get();
        $this->assertGreaterThanOrEqual(1, $results->count());
    }

    public function test_instrument_scope_by_type(): void
    {
        $o = InmetroOwner::factory()->create(['tenant_id' => $this->tenant->id]);
        $location = InmetroLocation::factory()->create(['owner_id' => $o->id]);
        InmetroInstrument::factory()->create([
            'tenant_id' => $this->tenant->id,
            'location_id' => $location->id,
            'instrument_type' => 'medidor_vazao',
        ]);
        $results = InmetroInstrument::where('instrument_type', 'medidor_vazao')->get();
        $this->assertGreaterThanOrEqual(1, $results->count());
    }
}
