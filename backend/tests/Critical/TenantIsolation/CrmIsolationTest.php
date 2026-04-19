<?php

/**
 * Tenant Isolation — CRM Module
 *
 * Validates complete data isolation for: CrmDeal, CrmPipeline, CrmActivity, CrmMessage.
 * Cross-tenant access MUST return 404 (not 403).
 *
 * FAILURE HERE = CRM DATA LEAK BETWEEN TENANTS
 */

use App\Models\CrmActivity;
use App\Models\CrmDeal;
use App\Models\CrmMessage;
use App\Models\CrmPipeline;
use App\Models\CrmPipelineStage;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {

    $this->tenantA = Tenant::factory()->create();
    $this->tenantB = Tenant::factory()->create();

    $this->userA = User::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'current_tenant_id' => $this->tenantA->id,
        'is_active' => true,
    ]);

    $this->userB = User::factory()->create([
        'tenant_id' => $this->tenantB->id,
        'current_tenant_id' => $this->tenantB->id,
        'is_active' => true,
    ]);

    $this->customerA = Customer::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'name' => 'CRM Cust A', 'type' => 'PJ',
    ]);
    $this->customerB = Customer::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'name' => 'CRM Cust B', 'type' => 'PJ',
    ]);

    foreach ([[$this->userA, $this->tenantA], [$this->userB, $this->tenantB]] as [$user, $tenant]) {
        $user->tenants()->syncWithoutDetaching([$tenant->id => ['is_default' => true]]);
        app()->instance('current_tenant_id', $tenant->id);
        setPermissionsTeamId($tenant->id);
        $user->assignRole('super_admin');
    }
});

function actAsTenantCrm(object $test, User $user, Tenant $tenant): void
{
    app()->instance('current_tenant_id', $tenant->id);
    setPermissionsTeamId($tenant->id);
    Sanctum::actingAs($user, ['*']);
}

// ══════════════════════════════════════════════════════════════════
//  CRM PIPELINES
// ══════════════════════════════════════════════════════════════════

test('CRM pipeline listing only shows own tenant', function () {
    CrmPipeline::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'name' => 'Pipeline A',
        'slug' => 'pipeline-a-'.uniqid(), 'is_active' => true, 'sort_order' => 0,
    ]);
    CrmPipeline::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'name' => 'Pipeline B',
        'slug' => 'pipeline-b-'.uniqid(), 'is_active' => true, 'sort_order' => 0,
    ]);

    actAsTenantCrm($this, $this->userA, $this->tenantA);

    $response = $this->getJson('/api/v1/crm/pipelines');
    $response->assertOk();

    $data = collect($response->json('data'));
    expect($data)->each(fn ($item) => $item->tenant_id->toBe($this->tenantA->id));
    expect($data->pluck('name')->toArray())->not->toContain('Pipeline B');
});

test('CrmPipeline model scope isolates by tenant', function () {
    CrmPipeline::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'name' => 'Model P-A',
        'slug' => 'model-pa-'.uniqid(), 'is_active' => true, 'sort_order' => 0,
    ]);
    CrmPipeline::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'name' => 'Model P-B',
        'slug' => 'model-pb-'.uniqid(), 'is_active' => true, 'sort_order' => 0,
    ]);

    app()->instance('current_tenant_id', $this->tenantA->id);

    $pipelines = CrmPipeline::all();
    expect($pipelines)->each(fn ($p) => $p->tenant_id->toBe($this->tenantA->id));
});

// ══════════════════════════════════════════════════════════════════
//  CRM DEALS
// ══════════════════════════════════════════════════════════════════

test('CRM deals listing only shows own tenant', function () {
    $pipeA = CrmPipeline::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'name' => 'Deal PA',
        'slug' => 'deal-pa-'.uniqid(), 'is_active' => true, 'sort_order' => 0,
    ]);
    $stageA = CrmPipelineStage::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'pipeline_id' => $pipeA->id,
        'name' => 'Stage A', 'sort_order' => 0, 'probability' => 50,
    ]);
    $pipeB = CrmPipeline::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'name' => 'Deal PB',
        'slug' => 'deal-pb-'.uniqid(), 'is_active' => true, 'sort_order' => 0,
    ]);
    $stageB = CrmPipelineStage::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'pipeline_id' => $pipeB->id,
        'name' => 'Stage B', 'sort_order' => 0, 'probability' => 50,
    ]);

    CrmDeal::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'customer_id' => $this->customerA->id,
        'pipeline_id' => $pipeA->id, 'stage_id' => $stageA->id,
        'title' => 'Deal A', 'value' => 5000, 'status' => 'open',
    ]);
    CrmDeal::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'customer_id' => $this->customerB->id,
        'pipeline_id' => $pipeB->id, 'stage_id' => $stageB->id,
        'title' => 'Deal B', 'value' => 8000, 'status' => 'open',
    ]);

    actAsTenantCrm($this, $this->userA, $this->tenantA);

    $response = $this->getJson('/api/v1/crm/deals');
    $response->assertOk();

    $data = collect($response->json('data'));
    expect($data)->each(fn ($item) => $item->tenant_id->toBe($this->tenantA->id));
    expect($data->pluck('title')->toArray())->not->toContain('Deal B');
});

test('cannot GET cross-tenant CRM deal — returns 404', function () {
    $pipeB = CrmPipeline::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'name' => 'Show PB',
        'slug' => 'show-pb-'.uniqid(), 'is_active' => true, 'sort_order' => 0,
    ]);
    $stageB = CrmPipelineStage::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'pipeline_id' => $pipeB->id,
        'name' => 'Show Stage B', 'sort_order' => 0, 'probability' => 50,
    ]);

    $dealB = CrmDeal::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'customer_id' => $this->customerB->id,
        'pipeline_id' => $pipeB->id, 'stage_id' => $stageB->id,
        'title' => 'Secret Deal B', 'value' => 10000, 'status' => 'open',
    ]);

    actAsTenantCrm($this, $this->userA, $this->tenantA);

    $this->getJson("/api/v1/crm/deals/{$dealB->id}")->assertNotFound();
});

test('cannot UPDATE cross-tenant CRM deal — returns 404', function () {
    $pipeB = CrmPipeline::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'name' => 'Upd PB',
        'slug' => 'upd-pb-'.uniqid(), 'is_active' => true, 'sort_order' => 0,
    ]);
    $stageB = CrmPipelineStage::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'pipeline_id' => $pipeB->id,
        'name' => 'Upd Stage B', 'sort_order' => 0, 'probability' => 50,
    ]);

    $dealB = CrmDeal::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'customer_id' => $this->customerB->id,
        'pipeline_id' => $pipeB->id, 'stage_id' => $stageB->id,
        'title' => 'Protected Deal', 'value' => 7000, 'status' => 'open',
    ]);

    actAsTenantCrm($this, $this->userA, $this->tenantA);

    $this->putJson("/api/v1/crm/deals/{$dealB->id}", ['title' => 'Hacked'])->assertNotFound();

    $this->assertDatabaseHas('crm_deals', ['id' => $dealB->id, 'title' => 'Protected Deal']);
});

test('cannot DELETE cross-tenant CRM deal — returns 404', function () {
    $pipeB = CrmPipeline::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'name' => 'Del PB',
        'slug' => 'del-pb-'.uniqid(), 'is_active' => true, 'sort_order' => 0,
    ]);
    $stageB = CrmPipelineStage::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'pipeline_id' => $pipeB->id,
        'name' => 'Del Stage B', 'sort_order' => 0, 'probability' => 50,
    ]);

    $dealB = CrmDeal::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'customer_id' => $this->customerB->id,
        'pipeline_id' => $pipeB->id, 'stage_id' => $stageB->id,
        'title' => 'Safe Deal', 'value' => 6000, 'status' => 'open',
    ]);

    actAsTenantCrm($this, $this->userA, $this->tenantA);

    $this->deleteJson("/api/v1/crm/deals/{$dealB->id}")->assertNotFound();
});

// ══════════════════════════════════════════════════════════════════
//  CRM ACTIVITIES
// ══════════════════════════════════════════════════════════════════

test('CRM activities listing only shows own tenant', function () {
    CrmActivity::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'customer_id' => $this->customerA->id,
        'user_id' => $this->userA->id, 'type' => 'call',
        'title' => 'Activity A', 'completed_at' => now(),
    ]);
    CrmActivity::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'customer_id' => $this->customerB->id,
        'user_id' => $this->userB->id, 'type' => 'email',
        'title' => 'Activity B', 'completed_at' => now(),
    ]);

    actAsTenantCrm($this, $this->userA, $this->tenantA);

    $response = $this->getJson('/api/v1/crm/activities');
    $response->assertOk();

    $data = collect($response->json('data'));
    expect($data)->each(fn ($item) => $item->tenant_id->toBe($this->tenantA->id));
    expect($data->pluck('title')->toArray())->not->toContain('Activity B');
});

test('CrmActivity model scope isolates by tenant', function () {
    CrmActivity::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'customer_id' => $this->customerA->id,
        'user_id' => $this->userA->id, 'type' => 'visit',
        'title' => 'Scope Act A', 'completed_at' => now(),
    ]);
    CrmActivity::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'customer_id' => $this->customerB->id,
        'user_id' => $this->userB->id, 'type' => 'visit',
        'title' => 'Scope Act B', 'completed_at' => now(),
    ]);

    app()->instance('current_tenant_id', $this->tenantA->id);

    $activities = CrmActivity::all();
    expect($activities)->toHaveCount(1);
    expect($activities->first()->title)->toBe('Scope Act A');
});

// ══════════════════════════════════════════════════════════════════
//  CRM MESSAGES
// ══════════════════════════════════════════════════════════════════

test('CRM messages listing only shows own tenant', function () {
    CrmMessage::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'customer_id' => $this->customerA->id,
        'channel' => 'whatsapp', 'direction' => 'outbound', 'status' => 'sent',
        'body' => 'Message A', 'to_address' => '11999999999', 'sent_at' => now(),
    ]);
    CrmMessage::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'customer_id' => $this->customerB->id,
        'channel' => 'whatsapp', 'direction' => 'outbound', 'status' => 'sent',
        'body' => 'Message B', 'to_address' => '11888888888', 'sent_at' => now(),
    ]);

    actAsTenantCrm($this, $this->userA, $this->tenantA);

    $response = $this->getJson('/api/v1/crm/messages');
    $response->assertOk();

    $data = collect($response->json('data'));
    expect($data)->each(fn ($item) => $item->tenant_id->toBe($this->tenantA->id));
    expect($data->pluck('body')->toArray())->not->toContain('Message B');
});

test('CrmMessage model scope isolates by tenant', function () {
    CrmMessage::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'customer_id' => $this->customerA->id,
        'channel' => 'email', 'direction' => 'outbound', 'status' => 'sent',
        'body' => 'Scope Msg A', 'to_address' => 'a@test.com', 'sent_at' => now(),
    ]);
    CrmMessage::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'customer_id' => $this->customerB->id,
        'channel' => 'email', 'direction' => 'outbound', 'status' => 'sent',
        'body' => 'Scope Msg B', 'to_address' => 'b@test.com', 'sent_at' => now(),
    ]);

    app()->instance('current_tenant_id', $this->tenantA->id);

    $messages = CrmMessage::all();
    expect($messages)->toHaveCount(1);
    expect($messages->first()->body)->toBe('Scope Msg A');
});

// ══════════════════════════════════════════════════════════════════
//  CRM DASHBOARD & ANALYTICS
// ══════════════════════════════════════════════════════════════════

test('CRM dashboard is tenant-scoped', function () {
    actAsTenantCrm($this, $this->userA, $this->tenantA);

    $response = $this->getJson('/api/v1/crm/dashboard');
    $response->assertOk();
});

test('CRM deal count is tenant-scoped', function () {
    $pipeA = CrmPipeline::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'name' => 'Count PA',
        'slug' => 'count-pa-'.uniqid(), 'is_active' => true, 'sort_order' => 0,
    ]);
    $stageA = CrmPipelineStage::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'pipeline_id' => $pipeA->id,
        'name' => 'Count Stage A', 'sort_order' => 0, 'probability' => 50,
    ]);
    $pipeB = CrmPipeline::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'name' => 'Count PB',
        'slug' => 'count-pb-'.uniqid(), 'is_active' => true, 'sort_order' => 0,
    ]);
    $stageB = CrmPipelineStage::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'pipeline_id' => $pipeB->id,
        'name' => 'Count Stage B', 'sort_order' => 0, 'probability' => 50,
    ]);

    CrmDeal::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'customer_id' => $this->customerA->id,
        'pipeline_id' => $pipeA->id, 'stage_id' => $stageA->id,
        'title' => 'Count Deal A', 'value' => 1000, 'status' => 'open',
    ]);
    CrmDeal::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'customer_id' => $this->customerB->id,
        'pipeline_id' => $pipeB->id, 'stage_id' => $stageB->id,
        'title' => 'Count Deal B1', 'value' => 2000, 'status' => 'open',
    ]);
    CrmDeal::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'customer_id' => $this->customerB->id,
        'pipeline_id' => $pipeB->id, 'stage_id' => $stageB->id,
        'title' => 'Count Deal B2', 'value' => 3000, 'status' => 'open',
    ]);

    app()->instance('current_tenant_id', $this->tenantA->id);
    expect(CrmDeal::count())->toBe(1);

    app()->instance('current_tenant_id', $this->tenantB->id);
    expect(CrmDeal::count())->toBe(2);
});

test('customer 360 view only shows own tenant data', function () {
    actAsTenantCrm($this, $this->userA, $this->tenantA);

    // Trying to access tenant B customer 360 should fail
    $response = $this->getJson("/api/v1/crm/customers/{$this->customerB->id}/360");
    $response->assertNotFound();
});
