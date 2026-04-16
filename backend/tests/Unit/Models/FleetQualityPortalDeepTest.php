<?php

namespace Tests\Unit\Models;

use App\Models\Fleet;
use App\Models\FleetFuelEntry;
use App\Models\FleetMaintenance;
use App\Models\FleetTrip;
use App\Models\PortalTicket;
use App\Models\PortalTicketComment;
use App\Models\QualityAudit;
use App\Models\QualityAuditItem;
use App\Models\QualityCorrectiveAction;
use App\Models\Survey;
use App\Models\SurveyResponse;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class FleetQualityPortalDeepTest extends TestCase
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

    // ── Fleet ──

    public function test_fleet_vehicle_creation(): void
    {
        $v = Fleet::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->assertNotNull($v);
    }

    public function test_fleet_has_fuel_entries(): void
    {
        $v = Fleet::factory()->create(['tenant_id' => $this->tenant->id]);
        FleetFuelEntry::factory()->count(3)->create([
            'fleet_id' => $v->id,
            'tenant_id' => $this->tenant->id,
        ]);
        $this->assertGreaterThanOrEqual(3, $v->fuelEntries()->count());
    }

    public function test_fleet_has_maintenances(): void
    {
        $v = Fleet::factory()->create(['tenant_id' => $this->tenant->id]);
        FleetMaintenance::factory()->create([
            'fleet_id' => $v->id,
            'tenant_id' => $this->tenant->id,
        ]);
        $this->assertGreaterThanOrEqual(1, $v->maintenances()->count());
    }

    public function test_fleet_has_trips(): void
    {
        $v = Fleet::factory()->create(['tenant_id' => $this->tenant->id]);
        FleetTrip::factory()->create([
            'fleet_id' => $v->id,
            'tenant_id' => $this->tenant->id,
            'driver_user_id' => $this->user->id,
        ]);
        $this->assertGreaterThanOrEqual(1, $v->trips()->count());
    }

    public function test_fuel_entry_liters_cost(): void
    {
        $v = Fleet::factory()->create(['tenant_id' => $this->tenant->id]);
        $fe = FleetFuelEntry::factory()->create([
            'fleet_id' => $v->id,
            'tenant_id' => $this->tenant->id,
            'liters' => '50.00',
            'cost' => '350.00',
        ]);
        $this->assertEquals('50.00', $fe->liters);
        $this->assertEquals('350.00', $fe->cost);
    }

    public function test_fleet_trip_distance(): void
    {
        $v = Fleet::factory()->create(['tenant_id' => $this->tenant->id]);
        $trip = FleetTrip::factory()->create([
            'fleet_id' => $v->id,
            'tenant_id' => $this->tenant->id,
            'driver_user_id' => $this->user->id,
            'distance_km' => '120.50',
        ]);
        $this->assertEquals('120.50', $trip->distance_km);
    }

    // ── Quality ──

    public function test_quality_audit_creation(): void
    {
        $qa = QualityAudit::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->assertNotNull($qa);
    }

    public function test_quality_audit_status(): void
    {
        $qa = QualityAudit::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'in_progress',
        ]);
        $qa->update(['status' => 'completed']);
        $this->assertEquals('completed', $qa->fresh()->status);
    }

    public function test_quality_checklist_item(): void
    {
        $qa = QualityAudit::factory()->create(['tenant_id' => $this->tenant->id]);
        $item = QualityAuditItem::create([
            'quality_audit_id' => $qa->id,
            'requirement' => 'ISO 9001',
            'result' => 'conform',
            'question' => 'Is it conforming?',
        ]);
        $this->assertEquals('conform', $item->result);
    }

    public function test_quality_non_conformity(): void
    {
        $qa = QualityAudit::factory()->create(['tenant_id' => $this->tenant->id]);
        $nc = QualityCorrectiveAction::factory()->create([
            'quality_audit_id' => $qa->id,
            'tenant_id' => $this->tenant->id,
            'status' => 'open',
        ]);
        $this->assertEquals('open', $nc->status);
    }

    // ── Portal ──

    public function test_portal_ticket_creation(): void
    {
        $ticket = PortalTicket::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->assertNotNull($ticket);
    }

    public function test_portal_ticket_status_transitions(): void
    {
        $ticket = PortalTicket::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'open',
        ]);
        $ticket->update(['status' => 'resolved']);
        $this->assertEquals('resolved', $ticket->fresh()->status);
    }

    public function test_portal_ticket_has_comments(): void
    {
        $ticket = PortalTicket::factory()->create(['tenant_id' => $this->tenant->id]);
        PortalTicketComment::factory()->create([
            'portal_ticket_id' => $ticket->id,
            'tenant_id' => $this->tenant->id,
        ]);
        $this->assertGreaterThanOrEqual(1, $ticket->comments()->count());
    }

    // ── Survey ──

    public function test_survey_creation(): void
    {
        $s = Survey::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->assertNotNull($s);
    }

    public function test_survey_has_responses(): void
    {
        $s = Survey::factory()->create(['tenant_id' => $this->tenant->id]);
        SurveyResponse::factory()->count(5)->create([
            'survey_id' => $s->id,
            'tenant_id' => $this->tenant->id,
        ]);
        $this->assertGreaterThanOrEqual(5, $s->responses()->count());
    }

    public function test_survey_average_rating(): void
    {
        $s = Survey::factory()->create(['tenant_id' => $this->tenant->id]);
        SurveyResponse::factory()->create([
            'survey_id' => $s->id,
            'tenant_id' => $this->tenant->id,
            'score' => 5,
        ]);
        SurveyResponse::factory()->create([
            'survey_id' => $s->id,
            'tenant_id' => $this->tenant->id,
            'score' => 3,
        ]);
        $avg = $s->responses()->avg('score');
        $this->assertEquals(4.0, (float) $avg);
    }
}
