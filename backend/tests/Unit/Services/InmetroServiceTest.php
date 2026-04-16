<?php

namespace Tests\Unit\Services;

use App\Models\InmetroCompetitor;
use App\Models\InmetroCompetitorSnapshot;
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

class InmetroServiceTest extends TestCase
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

    public function test_create_owner_with_instruments(): void
    {
        $owner = InmetroOwner::factory()->create(['tenant_id' => $this->tenant->id]);
        $location = InmetroLocation::factory()->create([
            'owner_id' => $owner->id,
            'tenant_id' => $this->tenant->id,
        ]);
        InmetroInstrument::factory()->count(3)->create([
            'location_id' => $location->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $this->assertCount(3, $owner->instruments);
    }

    public function test_competitor_with_snapshots(): void
    {
        $competitor = InmetroCompetitor::factory()->create(['tenant_id' => $this->tenant->id]);
        InmetroCompetitorSnapshot::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'competitor_id' => $competitor->id,
        ]);

        $snapshots = InmetroCompetitorSnapshot::where('competitor_id', $competitor->id)->get();
        $this->assertCount(2, $snapshots);
    }

    public function test_prospection_queue_creation(): void
    {
        $pq = InmetroProspectionQueue::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'pending',
        ]);

        $this->assertEquals('pending', $pq->status);
    }

    public function test_win_loss_record_creation(): void
    {
        $wl = InmetroWinLoss::factory()->create([
            'tenant_id' => $this->tenant->id,
            'outcome' => 'win',
        ]);

        $this->assertEquals('win', $wl->outcome);
    }

    public function test_owner_has_locations(): void
    {
        $owner = InmetroOwner::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->assertInstanceOf(HasMany::class, $owner->locations());
    }

    public function test_instrument_belongs_to_location(): void
    {
        $owner = InmetroOwner::factory()->create(['tenant_id' => $this->tenant->id]);
        $location = InmetroLocation::factory()->create([
            'owner_id' => $owner->id,
            'tenant_id' => $this->tenant->id,
        ]);
        $instrument = InmetroInstrument::factory()->create([
            'location_id' => $location->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $this->assertEquals($location->id, $instrument->location_id);
        $this->assertEquals($owner->id, $instrument->location->owner_id);
    }

    public function test_owner_can_be_deleted(): void
    {
        $owner = InmetroOwner::factory()->create(['tenant_id' => $this->tenant->id]);
        $ownerId = $owner->id;
        $owner->delete();

        $this->assertNull(InmetroOwner::find($ownerId));
    }
}
