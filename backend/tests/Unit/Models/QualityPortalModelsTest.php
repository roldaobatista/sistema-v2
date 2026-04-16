<?php

namespace Tests\Unit\Models;

use App\Models\CapaRecord;
use App\Models\ContactPolicy;
use App\Models\Customer;
use App\Models\DocumentVersion;
use App\Models\FollowUp;
use App\Models\ManagementReview;
use App\Models\PortalTicket;
use App\Models\QualityAudit;
use App\Models\QualityProcedure;
use App\Models\SatisfactionSurvey;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class QualityPortalModelsTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private Customer $customer;

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
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        app()->instance('current_tenant_id', $this->tenant->id);
        $this->actingAs($this->user);
    }

    // ── QualityAudit ──

    public function test_quality_audit_belongs_to_tenant(): void
    {
        $audit = QualityAudit::create([
            'tenant_id' => $this->tenant->id,
            'audit_number' => 'AUD-001',
            'title' => 'Auditoria Q1 2026',
            'type' => 'internal',
            'status' => 'planned',
            'auditor_id' => $this->user->id,
            'planned_date' => now()->addDays(30),
        ]);

        $this->assertEquals($this->tenant->id, $audit->tenant_id);
    }

    public function test_quality_audit_has_many_items(): void
    {
        $audit = QualityAudit::create([
            'tenant_id' => $this->tenant->id,
            'audit_number' => 'AUD-002',
            'title' => 'Auditoria Items Test',
            'type' => 'internal',
            'status' => 'planned',
            'auditor_id' => $this->user->id,
            'planned_date' => now()->addDays(30),
        ]);

        $this->assertInstanceOf(HasMany::class, $audit->items());
    }

    // ── QualityProcedure ──

    public function test_quality_procedure_belongs_to_tenant(): void
    {
        $proc = QualityProcedure::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'PQ-001',
            'title' => 'Procedimento de Calibração',
            'status' => 'active',
        ]);

        $this->assertEquals($this->tenant->id, $proc->tenant_id);
    }

    // ── CapaRecord ──

    public function test_capa_record_belongs_to_tenant(): void
    {
        $capa = CapaRecord::create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'title' => 'Test CAPA',
            'type' => 'corrective',
            'source' => 'manual',
            'status' => 'open',
        ]);
        $this->assertEquals($this->tenant->id, $capa->tenant_id);
    }

    public function test_capa_record_fillable_fields(): void
    {
        $capa = new CapaRecord;
        $this->assertContains('tenant_id', $capa->getFillable());
        $this->assertContains('title', $capa->getFillable());
    }

    // ── ManagementReview ──

    public function test_management_review_belongs_to_tenant(): void
    {
        $review = ManagementReview::create([
            'tenant_id' => $this->tenant->id,
            'title' => 'Análise Crítica Q1',
            'meeting_date' => '2026-03-15',
        ]);

        $this->assertEquals($this->tenant->id, $review->tenant_id);
    }

    // ── PortalTicket ──

    public function test_portal_ticket_belongs_to_customer(): void
    {
        $ticket = PortalTicket::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'subject' => 'Calibração pendente',
            'description' => 'Preciso de uma calibração urgente',
            'status' => 'open',
        ]);

        $this->assertInstanceOf(Customer::class, $ticket->customer);
    }

    public function test_portal_ticket_fillable_fields(): void
    {
        $ticket = new PortalTicket;
        $fillable = $ticket->getFillable();
        $this->assertContains('subject', $fillable);
        $this->assertContains('customer_id', $fillable);
    }

    // ── SatisfactionSurvey ──

    public function test_satisfaction_survey_belongs_to_work_order(): void
    {
        $wo = WorkOrder::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $this->customer->id]);
        $survey = SatisfactionSurvey::create([
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $wo->id,
            'customer_id' => $this->customer->id,
            'nps_score' => 5,
        ]);

        $this->assertInstanceOf(WorkOrder::class, $survey->workOrder);
    }

    public function test_satisfaction_survey_belongs_to_customer(): void
    {
        $wo = WorkOrder::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $this->customer->id]);
        $survey = SatisfactionSurvey::create([
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $wo->id,
            'customer_id' => $this->customer->id,
            'nps_score' => 4,
        ]);

        $this->assertInstanceOf(Customer::class, $survey->customer);
    }

    // ── FollowUp ──

    public function test_follow_up_belongs_to_assigned_user(): void
    {
        $followUp = FollowUp::create([
            'tenant_id' => $this->tenant->id,
            'assigned_to' => $this->user->id,
            'followable_type' => Customer::class,
            'followable_id' => $this->customer->id,
            'channel' => 'phone',
            'notes' => 'Follow-up de teste',
            'scheduled_at' => now()->addDays(7),
            'status' => 'pending',
        ]);

        $this->assertInstanceOf(User::class, $followUp->assignedTo);
    }

    // ── ContactPolicy ──

    public function test_contact_policy_belongs_to_tenant(): void
    {
        $policy = ContactPolicy::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Política padrão',
            'target_type' => 'all',
            'max_days_without_contact' => 90,
        ]);

        $this->assertEquals($this->tenant->id, $policy->tenant_id);
    }

    // ── DocumentVersion ──

    public function test_document_version_belongs_to_tenant(): void
    {
        $doc = DocumentVersion::create([
            'tenant_id' => $this->tenant->id,
            'document_code' => 'Q-001',
            'title' => 'Procedimento Q-001 v2',
            'category' => 'procedure',
            'version' => '2.0',
            'status' => 'current',
            'created_by' => $this->user->id,
        ]);

        $this->assertEquals($this->tenant->id, $doc->tenant_id);
    }
}
