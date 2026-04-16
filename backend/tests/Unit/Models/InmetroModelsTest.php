<?php

namespace Tests\Unit\Models;

use App\Models\InmetroCompetitor;
use App\Models\InmetroCompetitorSnapshot;
use App\Models\InmetroComplianceChecklist;
use App\Models\InmetroInstrument;
use App\Models\InmetroLocation;
use App\Models\InmetroOwner;
use App\Models\InmetroProspectionQueue;
use App\Models\InmetroWinLoss;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class InmetroModelsTest extends TestCase
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

    // ── InmetroOwner — Relationships ──

    public function test_owner_belongs_to_tenant(): void
    {
        $owner = InmetroOwner::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->assertEquals($this->tenant->id, $owner->tenant_id);
    }

    public function test_owner_has_many_instruments(): void
    {
        $owner = InmetroOwner::factory()->create(['tenant_id' => $this->tenant->id]);
        InmetroInstrument::factory()->create([
            'tenant_id' => $this->tenant->id,
            'owner_id' => $owner->id,
        ]);

        $this->assertGreaterThanOrEqual(1, $owner->instruments()->count());
    }

    public function test_owner_has_many_locations(): void
    {
        $owner = InmetroOwner::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->assertInstanceOf(HasMany::class, $owner->locations());
    }

    public function test_owner_fillable_fields(): void
    {
        $owner = new InmetroOwner;
        $fillable = $owner->getFillable();
        $this->assertContains('tenant_id', $fillable);
        $this->assertContains('name', $fillable);
    }

    // ── InmetroInstrument — Relationships ──

    public function test_instrument_belongs_to_owner(): void
    {
        $owner = InmetroOwner::factory()->create(['tenant_id' => $this->tenant->id]);
        $instrument = InmetroInstrument::factory()->create([
            'tenant_id' => $this->tenant->id,
            'owner_id' => $owner->id,
        ]);

        $this->assertInstanceOf(InmetroOwner::class, $instrument->owner);
    }

    public function test_instrument_belongs_to_tenant(): void
    {
        $owner = InmetroOwner::factory()->create(['tenant_id' => $this->tenant->id]);
        $instrument = InmetroInstrument::factory()->create([
            'tenant_id' => $this->tenant->id,
            'owner_id' => $owner->id,
        ]);

        $this->assertEquals($this->tenant->id, $instrument->tenant_id);
    }

    // ── InmetroCompetitor — Relationships ──

    public function test_competitor_belongs_to_tenant(): void
    {
        $competitor = InmetroCompetitor::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->assertEquals($this->tenant->id, $competitor->tenant_id);
    }

    public function test_competitor_has_many_snapshots(): void
    {
        $competitor = InmetroCompetitor::factory()->create(['tenant_id' => $this->tenant->id]);
        InmetroCompetitorSnapshot::factory()->create([
            'tenant_id' => $this->tenant->id,
            'competitor_id' => $competitor->id,
        ]);

        $this->assertGreaterThanOrEqual(1, $competitor->snapshots()->count());
    }

    // ── InmetroProspectionQueue — Relationships ──

    public function test_prospection_queue_belongs_to_tenant(): void
    {
        $pq = InmetroProspectionQueue::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
        $this->assertEquals($this->tenant->id, $pq->tenant_id);
    }

    // ── InmetroWinLoss — Relationships ──

    public function test_win_loss_belongs_to_tenant(): void
    {
        $wl = InmetroWinLoss::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->assertEquals($this->tenant->id, $wl->tenant_id);
    }

    // ── InmetroLocation — Relationships ──

    public function test_location_belongs_to_owner(): void
    {
        $owner = InmetroOwner::factory()->create(['tenant_id' => $this->tenant->id]);
        $location = InmetroLocation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'owner_id' => $owner->id,
        ]);

        $this->assertInstanceOf(InmetroOwner::class, $location->owner);
    }

    // ── InmetroComplianceChecklist — Relationships ──

    public function test_compliance_checklist_belongs_to_tenant(): void
    {
        $cl = InmetroComplianceChecklist::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->assertEquals($this->tenant->id, $cl->tenant_id);
    }
}
