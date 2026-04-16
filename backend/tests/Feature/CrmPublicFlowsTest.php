<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckPermission;
use App\Models\CrmInteractiveProposal;
use App\Models\CrmMessage;
use App\Models\CrmPipeline;
use App\Models\CrmSequence;
use App\Models\CrmWebForm;
use App\Models\Customer;
use App\Models\Quote;
use App\Models\Tenant;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CrmPublicFlowsTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withMiddleware([
            CheckPermission::class,
        ]);

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);
        $this->user->tenants()->attach($this->tenant);

        app()->instance('current_tenant_id', $this->tenant->id);
        setPermissionsTeamId($this->tenant->id);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function grant(string ...$permissions): void
    {
        setPermissionsTeamId($this->tenant->id);

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $this->user->syncPermissions($permissions);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_web_form_requests_are_scoped_to_the_current_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $foreignPipeline = CrmPipeline::factory()->create(['tenant_id' => $otherTenant->id]);
        $foreignSequence = CrmSequence::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Sequencia Externa',
            'status' => 'active',
        ]);
        $foreignAssignee = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'current_tenant_id' => $otherTenant->id,
        ]);

        $this->grant('crm.form.manage', 'crm.create');
        Sanctum::actingAs($this->user, ['*']);

        $this->postJson('/api/v1/crm-features/web-forms', [
            'name' => 'Lead Site',
            'fields' => [
                ['name' => 'email', 'type' => 'email', 'label' => 'Email', 'required' => true],
            ],
            'pipeline_id' => $foreignPipeline->id,
            'sequence_id' => $foreignSequence->id,
            'assign_to' => $foreignAssignee->id,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['pipeline_id', 'sequence_id', 'assign_to']);
    }

    public function test_web_form_update_rejects_cross_tenant_model_binding(): void
    {
        $otherTenant = Tenant::factory()->create();
        $foreignForm = CrmWebForm::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Outro Formulario',
            'slug' => 'outro-formulario',
            'fields' => [
                ['name' => 'email', 'type' => 'email', 'label' => 'Email'],
            ],
            'is_active' => true,
        ]);

        $this->grant('crm.form.manage', 'crm.update');
        Sanctum::actingAs($this->user, ['*']);

        $this->putJson("/api/v1/crm-features/web-forms/{$foreignForm->id}", [
            'name' => 'Tentativa de invasao',
        ])->assertNotFound();

        $this->assertDatabaseHas('crm_web_forms', [
            'id' => $foreignForm->id,
            'name' => 'Outro Formulario',
        ]);
    }

    public function test_public_web_form_submission_ignores_legacy_cross_tenant_links(): void
    {
        $otherTenant = Tenant::factory()->create();
        $foreignPipeline = CrmPipeline::factory()->create(['tenant_id' => $otherTenant->id]);
        $foreignSequence = CrmSequence::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Sequencia Externa',
            'status' => 'active',
        ]);
        $foreignAssignee = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'current_tenant_id' => $otherTenant->id,
        ]);

        $form = CrmWebForm::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Lead Publico',
            'slug' => 'lead-publico',
            'fields' => [
                ['name' => 'email', 'type' => 'email', 'label' => 'Email'],
            ],
            'pipeline_id' => $foreignPipeline->id,
            'sequence_id' => $foreignSequence->id,
            'assign_to' => $foreignAssignee->id,
            'is_active' => true,
        ]);

        $this->postJson("/api/v1/web-forms/{$form->slug}/submit", [
            'name' => 'Lead Teste',
            'email' => 'lead@example.com',
            'phone' => '65999999999',
        ])->assertOk();

        $customer = Customer::query()
            ->where('tenant_id', $this->tenant->id)
            ->where('email', 'lead@example.com')
            ->first();

        $this->assertNotNull($customer);
        $this->assertNull($customer->assigned_seller_id);
        $this->assertDatabaseCount('crm_sequence_enrollments', 0);
        $this->assertDatabaseCount('crm_deals', 0);
        $this->assertDatabaseHas('crm_tracking_events', [
            'tenant_id' => $this->tenant->id,
            'event_type' => 'form_submitted',
            'customer_id' => $customer->id,
        ]);
    }

    public function test_public_proposal_can_only_be_answered_once_and_approves_quote_with_metadata(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $quote = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'status' => Quote::STATUS_SENT,
            'approved_at' => null,
            'valid_until' => now()->addDays(5),
        ]);

        $proposal = CrmInteractiveProposal::create([
            'tenant_id' => $this->tenant->id,
            'quote_id' => $quote->id,
            'status' => CrmInteractiveProposal::STATUS_SENT,
            'expires_at' => now()->addDays(2),
        ]);

        $this->postJson("/api/v1/proposals/{$proposal->token}/respond", [
            'action' => 'accept',
            'client_notes' => 'Pode seguir',
        ])->assertOk()
            ->assertJsonPath('message', 'Proposta aceita!');

        $proposal->refresh();
        $quote->refresh();

        $this->assertSame(CrmInteractiveProposal::STATUS_ACCEPTED, $proposal->status);
        $this->assertNotNull($proposal->accepted_at);
        $this->assertSame(Quote::STATUS_APPROVED, $quote->status->value);
        $this->assertNotNull($quote->approved_at);
        $this->assertDatabaseHas('crm_tracking_events', [
            'tenant_id' => $this->tenant->id,
            'trackable_type' => CrmInteractiveProposal::class,
            'trackable_id' => $proposal->id,
            'event_type' => 'proposal_accepted',
            'customer_id' => $customer->id,
        ]);

        $this->postJson("/api/v1/proposals/{$proposal->token}/respond", [
            'action' => 'reject',
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Proposta ja respondida ou indisponivel.');

        $this->assertSame(CrmInteractiveProposal::STATUS_ACCEPTED, $proposal->fresh()->status);
        $this->assertSame(Quote::STATUS_APPROVED, $quote->fresh()->status->value);
    }

    public function test_tracking_pixel_preserves_customer_and_deal_context(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $message = CrmMessage::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'deal_id' => null,
            'channel' => 'email',
            'direction' => 'outbound',
            'subject' => 'Teste',
            'body' => 'Corpo',
            'status' => 'sent',
        ]);

        $this->get("/api/v1/crm-pixel/{$this->tenant->id}-msg-{$message->id}")
            ->assertOk();

        $this->assertDatabaseHas('crm_tracking_events', [
            'tenant_id' => $this->tenant->id,
            'trackable_type' => CrmMessage::class,
            'trackable_id' => $message->id,
            'event_type' => 'email_opened',
            'customer_id' => $customer->id,
        ]);
    }
}
