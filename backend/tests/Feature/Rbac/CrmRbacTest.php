<?php

use App\Http\Middleware\EnsureTenantScope;
use App\Models\CrmDeal;
use App\Models\CrmPipeline;
use App\Models\CrmPipelineStage;
use App\Models\CrmSequence;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->withoutMiddleware([
        EnsureTenantScope::class,
    ]);

    $this->tenant = Tenant::factory()->create();
    app()->instance('current_tenant_id', $this->tenant->id);
    setPermissionsTeamId($this->tenant->id);

    $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
});

function crmUser(Tenant $tenant, array $permissions = []): User
{
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'current_tenant_id' => $tenant->id,
        'is_active' => true,
    ]);

    setPermissionsTeamId($tenant->id);

    foreach ($permissions as $perm) {
        Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        $user->givePermissionTo($perm);
    }

    return $user;
}

// ============================================================
// CRM Deals - View
// ============================================================

test('user WITH crm.deal.view can list deals', function () {
    $user = crmUser($this->tenant, ['crm.deal.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/crm/deals')->assertOk();
});

test('user WITHOUT crm.deal.view gets 403 on list deals', function () {
    $user = crmUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/crm/deals')->assertForbidden();
});

test('user WITH crm.deal.view can show a deal', function () {
    $user = crmUser($this->tenant, ['crm.deal.view']);
    Sanctum::actingAs($user, ['*']);

    $pipeline = CrmPipeline::factory()->create(['tenant_id' => $this->tenant->id]);
    $stage = CrmPipelineStage::factory()->create([
        'tenant_id' => $this->tenant->id,
        'pipeline_id' => $pipeline->id,
    ]);
    $deal = CrmDeal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'pipeline_id' => $pipeline->id,
        'stage_id' => $stage->id,
    ]);

    $this->getJson("/api/v1/crm/deals/{$deal->id}")->assertOk();
});

test('user WITHOUT crm.deal.view gets 403 on show deal', function () {
    $user = crmUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $pipeline = CrmPipeline::factory()->create(['tenant_id' => $this->tenant->id]);
    $stage = CrmPipelineStage::factory()->create([
        'tenant_id' => $this->tenant->id,
        'pipeline_id' => $pipeline->id,
    ]);
    $deal = CrmDeal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'pipeline_id' => $pipeline->id,
        'stage_id' => $stage->id,
    ]);

    $this->getJson("/api/v1/crm/deals/{$deal->id}")->assertForbidden();
});

test('user WITH crm.deal.view can access CRM dashboard', function () {
    $user = crmUser($this->tenant, ['crm.deal.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/crm/dashboard')->assertOk();
});

test('user WITHOUT crm.deal.view gets 403 on CRM dashboard', function () {
    $user = crmUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/crm/dashboard')->assertForbidden();
});

test('user WITH crm.deal.view can list activities', function () {
    $user = crmUser($this->tenant, ['crm.deal.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/crm/activities')->assertOk();
});

test('user WITHOUT crm.deal.view gets 403 on list activities', function () {
    $user = crmUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/crm/activities')->assertForbidden();
});

test('user WITH crm.deal.view and cadastros.customer.view can access customer 360', function () {
    $user = crmUser($this->tenant, ['crm.deal.view', 'cadastros.customer.view']);
    Sanctum::actingAs($user, ['*']);

    // Pre-create permissions that customer360 checks via hasPermissionTo (avoids PermissionDoesNotExist)
    foreach (['platform.dashboard.view', 'finance.receivable.view', 'fiscal.note.view', 'customer.document.view'] as $perm) {
        Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
    }

    $this->getJson("/api/v1/crm/customers/{$this->customer->id}/360")->assertOk();
});

test('user WITHOUT crm.deal.view gets 403 on customer 360', function () {
    $user = crmUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson("/api/v1/crm/customers/{$this->customer->id}/360")->assertForbidden();
});

// ============================================================
// CRM Deals - Create
// ============================================================

test('user WITH crm.deal.create can store deal', function () {
    $user = crmUser($this->tenant, ['crm.deal.create']);
    Sanctum::actingAs($user, ['*']);

    $pipeline = CrmPipeline::factory()->create(['tenant_id' => $this->tenant->id]);
    $stage = CrmPipelineStage::factory()->create([
        'tenant_id' => $this->tenant->id,
        'pipeline_id' => $pipeline->id,
    ]);

    $this->postJson('/api/v1/crm/deals', [
        'title' => 'Novo negocio',
        'customer_id' => $this->customer->id,
        'pipeline_id' => $pipeline->id,
        'stage_id' => $stage->id,
        'value' => 5000.00,
    ])->assertStatus(201);
});

test('user WITHOUT crm.deal.create gets 403 on store deal', function () {
    $user = crmUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/crm/deals', [
        'title' => 'Novo negocio',
    ])->assertForbidden();
});

test('user WITH crm.deal.create can store activity', function () {
    $user = crmUser($this->tenant, ['crm.deal.create']);
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/crm/activities', [
        'type' => 'ligacao',
        'title' => 'Ligacao de followup',
        'description' => 'Ligacao de followup detalhes',
        'customer_id' => $this->customer->id,
        'scheduled_at' => now()->addDay()->toDateTimeString(),
    ])->assertStatus(201);
});

test('user WITHOUT crm.deal.create gets 403 on store activity', function () {
    $user = crmUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/crm/activities', [
        'type' => 'call',
    ])->assertForbidden();
});

// ============================================================
// CRM Deals - Update
// ============================================================

test('user WITH crm.deal.update can update deal', function () {
    $user = crmUser($this->tenant, ['crm.deal.update']);
    Sanctum::actingAs($user, ['*']);

    $pipeline = CrmPipeline::factory()->create(['tenant_id' => $this->tenant->id]);
    $stage = CrmPipelineStage::factory()->create([
        'tenant_id' => $this->tenant->id,
        'pipeline_id' => $pipeline->id,
    ]);
    $deal = CrmDeal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'pipeline_id' => $pipeline->id,
        'stage_id' => $stage->id,
    ]);

    $this->putJson("/api/v1/crm/deals/{$deal->id}", [
        'title' => 'Negocio atualizado',
    ])->assertOk();
});

test('user WITHOUT crm.deal.update gets 403 on update deal', function () {
    $user = crmUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $pipeline = CrmPipeline::factory()->create(['tenant_id' => $this->tenant->id]);
    $stage = CrmPipelineStage::factory()->create([
        'tenant_id' => $this->tenant->id,
        'pipeline_id' => $pipeline->id,
    ]);
    $deal = CrmDeal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'pipeline_id' => $pipeline->id,
        'stage_id' => $stage->id,
    ]);

    $this->putJson("/api/v1/crm/deals/{$deal->id}", [
        'title' => 'Negocio atualizado',
    ])->assertForbidden();
});

test('user WITH crm.deal.update can mark deal as won', function () {
    $user = crmUser($this->tenant, ['crm.deal.update']);
    Sanctum::actingAs($user, ['*']);

    $pipeline = CrmPipeline::factory()->create(['tenant_id' => $this->tenant->id]);
    $stage = CrmPipelineStage::factory()->create([
        'tenant_id' => $this->tenant->id,
        'pipeline_id' => $pipeline->id,
    ]);
    $deal = CrmDeal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'pipeline_id' => $pipeline->id,
        'stage_id' => $stage->id,
    ]);

    $this->putJson("/api/v1/crm/deals/{$deal->id}/won")->assertOk();
});

test('user WITHOUT crm.deal.update gets 403 on mark deal as won', function () {
    $user = crmUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $pipeline = CrmPipeline::factory()->create(['tenant_id' => $this->tenant->id]);
    $stage = CrmPipelineStage::factory()->create([
        'tenant_id' => $this->tenant->id,
        'pipeline_id' => $pipeline->id,
    ]);
    $deal = CrmDeal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'pipeline_id' => $pipeline->id,
        'stage_id' => $stage->id,
    ]);

    $this->putJson("/api/v1/crm/deals/{$deal->id}/won")->assertForbidden();
});

test('user WITH crm.deal.update can mark deal as lost', function () {
    $user = crmUser($this->tenant, ['crm.deal.update']);
    Sanctum::actingAs($user, ['*']);

    $pipeline = CrmPipeline::factory()->create(['tenant_id' => $this->tenant->id]);
    $stage = CrmPipelineStage::factory()->create([
        'tenant_id' => $this->tenant->id,
        'pipeline_id' => $pipeline->id,
    ]);
    $deal = CrmDeal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'pipeline_id' => $pipeline->id,
        'stage_id' => $stage->id,
    ]);

    $this->putJson("/api/v1/crm/deals/{$deal->id}/lost", [
        'lost_reason' => 'Preco',
    ])->assertOk();
});

test('user WITHOUT crm.deal.update gets 403 on mark deal as lost', function () {
    $user = crmUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $pipeline = CrmPipeline::factory()->create(['tenant_id' => $this->tenant->id]);
    $stage = CrmPipelineStage::factory()->create([
        'tenant_id' => $this->tenant->id,
        'pipeline_id' => $pipeline->id,
    ]);
    $deal = CrmDeal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'pipeline_id' => $pipeline->id,
        'stage_id' => $stage->id,
    ]);

    $this->putJson("/api/v1/crm/deals/{$deal->id}/lost")->assertForbidden();
});

// ============================================================
// CRM Deals - Delete
// ============================================================

test('user WITH crm.deal.delete can delete deal', function () {
    $user = crmUser($this->tenant, ['crm.deal.delete']);
    Sanctum::actingAs($user, ['*']);

    $pipeline = CrmPipeline::factory()->create(['tenant_id' => $this->tenant->id]);
    $stage = CrmPipelineStage::factory()->create([
        'tenant_id' => $this->tenant->id,
        'pipeline_id' => $pipeline->id,
    ]);
    $deal = CrmDeal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'pipeline_id' => $pipeline->id,
        'stage_id' => $stage->id,
    ]);

    $this->deleteJson("/api/v1/crm/deals/{$deal->id}")->assertNoContent();
});

test('user WITHOUT crm.deal.delete gets 403 on delete deal', function () {
    $user = crmUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $pipeline = CrmPipeline::factory()->create(['tenant_id' => $this->tenant->id]);
    $stage = CrmPipelineStage::factory()->create([
        'tenant_id' => $this->tenant->id,
        'pipeline_id' => $pipeline->id,
    ]);
    $deal = CrmDeal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'pipeline_id' => $pipeline->id,
        'stage_id' => $stage->id,
    ]);

    $this->deleteJson("/api/v1/crm/deals/{$deal->id}")->assertForbidden();
});

// ============================================================
// CRM Pipelines
// ============================================================

test('user WITH crm.pipeline.view can list pipelines', function () {
    $user = crmUser($this->tenant, ['crm.pipeline.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/crm/pipelines')->assertOk();
});

test('user WITHOUT crm.pipeline.view gets 403 on list pipelines', function () {
    $user = crmUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/crm/pipelines')->assertForbidden();
});

test('user WITH crm.pipeline.create can store pipeline', function () {
    $user = crmUser($this->tenant, ['crm.pipeline.create']);
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/crm/pipelines', [
        'name' => 'Pipeline de Vendas',
        'slug' => 'pipeline-vendas',
        'color' => '#3498db',
        'stages' => [
            ['name' => 'Prospeccao', 'color' => '#3498db'],
        ],
    ])->assertStatus(201);
});

test('user WITHOUT crm.pipeline.create gets 403 on store pipeline', function () {
    $user = crmUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/crm/pipelines', [
        'name' => 'Pipeline de Vendas',
    ])->assertForbidden();
});

test('user WITH crm.pipeline.update can update pipeline', function () {
    $user = crmUser($this->tenant, ['crm.pipeline.update']);
    Sanctum::actingAs($user, ['*']);

    $pipeline = CrmPipeline::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->putJson("/api/v1/crm/pipelines/{$pipeline->id}", [
        'name' => 'Pipeline atualizado',
    ])->assertOk();
});

test('user WITHOUT crm.pipeline.update gets 403 on update pipeline', function () {
    $user = crmUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $pipeline = CrmPipeline::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->putJson("/api/v1/crm/pipelines/{$pipeline->id}", [
        'name' => 'Pipeline atualizado',
    ])->assertForbidden();
});

test('user WITH crm.pipeline.delete can delete pipeline', function () {
    $user = crmUser($this->tenant, ['crm.pipeline.delete']);
    Sanctum::actingAs($user, ['*']);

    $pipeline = CrmPipeline::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->deleteJson("/api/v1/crm/pipelines/{$pipeline->id}")->assertNoContent();
});

test('user WITHOUT crm.pipeline.delete gets 403 on delete pipeline', function () {
    $user = crmUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $pipeline = CrmPipeline::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->deleteJson("/api/v1/crm/pipelines/{$pipeline->id}")->assertForbidden();
});

// ============================================================
// CRM Messages
// ============================================================

test('user WITH crm.message.view can list messages', function () {
    $user = crmUser($this->tenant, ['crm.message.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/crm/messages')->assertOk();
});

test('user WITHOUT crm.message.view gets 403 on list messages', function () {
    $user = crmUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/crm/messages')->assertForbidden();
});

test('user WITH crm.message.view can list message templates', function () {
    $user = crmUser($this->tenant, ['crm.message.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/crm/message-templates')->assertOk();
});

test('user WITHOUT crm.message.view gets 403 on message templates', function () {
    $user = crmUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/crm/message-templates')->assertForbidden();
});

test('user WITH crm.message.send can send message', function () {
    $user = crmUser($this->tenant, ['crm.message.send']);
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/crm/messages/send', [
        'channel' => 'email',
        'to' => 'cliente@example.com',
        'subject' => 'Proposta comercial',
        'body' => 'Segue proposta em anexo.',
        'customer_id' => $this->customer->id,
    ])->assertSuccessful();
});

test('user WITHOUT crm.message.send gets 403 on send message', function () {
    $user = crmUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/crm/messages/send', [
        'channel' => 'email',
        'to' => 'cliente@example.com',
    ])->assertForbidden();
});

// ============================================================
// CRM Sequences (Cadences)
// ============================================================

test('user WITH crm.sequence.view can list sequences', function () {
    $user = crmUser($this->tenant, ['crm.sequence.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/crm-features/sequences')->assertOk();
});

test('user WITHOUT crm.sequence.view gets 403 on list sequences', function () {
    $user = crmUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/crm-features/sequences')->assertForbidden();
});

test('user WITH crm.sequence.view can show a sequence', function () {
    $user = crmUser($this->tenant, ['crm.sequence.view']);
    Sanctum::actingAs($user, ['*']);

    $seq = CrmSequence::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->getJson("/api/v1/crm-features/sequences/{$seq->id}")->assertOk();
});

test('user WITHOUT crm.sequence.view gets 403 on show sequence', function () {
    $user = crmUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $seq = CrmSequence::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->getJson("/api/v1/crm-features/sequences/{$seq->id}")->assertForbidden();
});

test('user WITH crm.sequence.manage can store sequence', function () {
    $user = crmUser($this->tenant, ['crm.sequence.manage']);
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/crm-features/sequences', [
        'name' => 'Cadencia followup',
        'steps' => [
            [
                'step_order' => 1,
                'delay_days' => 0,
                'channel' => 'email',
                'action_type' => 'send_message',
                'subject' => 'Primeiro contato',
                'body' => 'Ola, tudo bem?',
            ],
        ],
    ])->assertStatus(201);
});

test('user WITHOUT crm.sequence.manage gets 403 on store sequence', function () {
    $user = crmUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/crm-features/sequences', [
        'name' => 'Cadencia followup',
    ])->assertForbidden();
});

test('user WITH crm.sequence.manage can update sequence', function () {
    $user = crmUser($this->tenant, ['crm.sequence.manage']);
    Sanctum::actingAs($user, ['*']);

    $seq = CrmSequence::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->putJson("/api/v1/crm-features/sequences/{$seq->id}", [
        'name' => 'Cadencia atualizada',
    ])->assertOk();
});

test('user WITHOUT crm.sequence.manage gets 403 on update sequence', function () {
    $user = crmUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $seq = CrmSequence::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->putJson("/api/v1/crm-features/sequences/{$seq->id}", [
        'name' => 'Cadencia atualizada',
    ])->assertForbidden();
});

test('user WITH crm.sequence.manage can delete sequence', function () {
    $user = crmUser($this->tenant, ['crm.sequence.manage']);
    Sanctum::actingAs($user, ['*']);

    $seq = CrmSequence::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->deleteJson("/api/v1/crm-features/sequences/{$seq->id}")->assertOk();
});

test('user WITHOUT crm.sequence.manage gets 403 on delete sequence', function () {
    $user = crmUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $seq = CrmSequence::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->deleteJson("/api/v1/crm-features/sequences/{$seq->id}")->assertForbidden();
});

// ============================================================
// CRM Lead Scoring
// ============================================================

test('user WITH crm.scoring.view can list scoring rules', function () {
    $user = crmUser($this->tenant, ['crm.scoring.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/crm-features/scoring/rules')->assertOk();
});

test('user WITHOUT crm.scoring.view gets 403 on scoring rules', function () {
    $user = crmUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/crm-features/scoring/rules')->assertForbidden();
});

test('user WITH crm.scoring.view can access leaderboard', function () {
    $user = crmUser($this->tenant, ['crm.scoring.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/crm-features/scoring/leaderboard')->assertOk();
});

test('user WITHOUT crm.scoring.view gets 403 on leaderboard', function () {
    $user = crmUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/crm-features/scoring/leaderboard')->assertForbidden();
});

// ============================================================
// CRM Forecast
// ============================================================

test('user WITH crm.forecast.view can access forecast', function () {
    $user = crmUser($this->tenant, ['crm.forecast.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/crm-features/forecast')->assertOk();
});

test('user WITHOUT crm.forecast.view gets 403 on forecast', function () {
    $user = crmUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/crm-features/forecast')->assertForbidden();
});

// ============================================================
// CRM Territories
// ============================================================

test('user WITH crm.territory.view can list territories', function () {
    $user = crmUser($this->tenant, ['crm.territory.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/crm-features/territories')->assertOk();
});

test('user WITHOUT crm.territory.view gets 403 on territories', function () {
    $user = crmUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/crm-features/territories')->assertForbidden();
});

// ============================================================
// CRM Sales Goals
// ============================================================

test('user WITH crm.goal.view can list sales goals', function () {
    $user = crmUser($this->tenant, ['crm.goal.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/crm-features/goals')->assertOk();
});

test('user WITHOUT crm.goal.view gets 403 on sales goals', function () {
    $user = crmUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/crm-features/goals')->assertForbidden();
});

// ============================================================
// CRM Referrals
// ============================================================

test('user WITH crm.referral.view can list referrals', function () {
    $user = crmUser($this->tenant, ['crm.referral.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/crm-features/referrals')->assertOk();
});

test('user WITHOUT crm.referral.view gets 403 on referrals', function () {
    $user = crmUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/crm-features/referrals')->assertForbidden();
});

// ============================================================
// CRM Contract Renewals
// ============================================================

test('user WITH crm.renewal.view can list contract renewals', function () {
    $user = crmUser($this->tenant, ['crm.renewal.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/crm-features/renewals')->assertOk();
});

test('user WITHOUT crm.renewal.view gets 403 on contract renewals', function () {
    $user = crmUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/crm-features/renewals')->assertForbidden();
});

// ============================================================
// CRM Web Forms
// ============================================================

test('user WITH crm.form.view can list web forms', function () {
    $user = crmUser($this->tenant, ['crm.form.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/crm-features/web-forms')->assertOk();
});

test('user WITHOUT crm.form.view gets 403 on web forms', function () {
    $user = crmUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/crm-features/web-forms')->assertForbidden();
});

// ============================================================
// CRM Proposals
// ============================================================

test('user WITH crm.proposal.view can list interactive proposals', function () {
    $user = crmUser($this->tenant, ['crm.proposal.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/crm-features/proposals')->assertOk();
});

test('user WITHOUT crm.proposal.view gets 403 on proposals', function () {
    $user = crmUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/crm-features/proposals')->assertForbidden();
});

// ============================================================
// CRM Field Management - Checkins
// ============================================================

test('user WITH crm.deal.view can list checkins', function () {
    $user = crmUser($this->tenant, ['crm.deal.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/crm-field/checkins')->assertOk();
});

test('user WITHOUT crm.deal.view gets 403 on checkins', function () {
    $user = crmUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/crm-field/checkins')->assertForbidden();
});

// ============================================================
// CRM Field Management - Routes
// ============================================================

test('user WITH crm.deal.view can list visit routes', function () {
    $user = crmUser($this->tenant, ['crm.deal.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/crm-field/routes')->assertOk();
});

test('user WITHOUT crm.deal.view gets 403 on visit routes', function () {
    $user = crmUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/crm-field/routes')->assertForbidden();
});

// ============================================================
// CRM Field Management - Reports
// ============================================================

test('user WITH crm.deal.view can list visit reports', function () {
    $user = crmUser($this->tenant, ['crm.deal.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/crm-field/reports')->assertOk();
});

test('user WITHOUT crm.deal.view gets 403 on visit reports', function () {
    $user = crmUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/crm-field/reports')->assertForbidden();
});
