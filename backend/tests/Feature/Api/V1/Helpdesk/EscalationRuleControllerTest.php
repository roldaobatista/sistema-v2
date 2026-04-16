<?php

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\EscalationRule;
use App\Models\SlaPolicy;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Gate::before(fn () => true);
    $this->withoutMiddleware([EnsureTenantScope::class, CheckPermission::class]);
    $this->tenant = Tenant::factory()->create();
    $this->user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'current_tenant_id' => $this->tenant->id,
        'is_active' => true,
    ]);
    app()->instance('current_tenant_id', $this->tenant->id);
    Sanctum::actingAs($this->user, ['*']);
});

it('can list escalation rules with pagination', function () {
    EscalationRule::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

    $response = $this->getJson('/api/v1/helpdesk/escalation-rules');

    $response->assertOk()
        ->assertJsonStructure([
            'data',
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ])
        ->assertJsonCount(3, 'data');
});

it('can create an escalation rule', function () {
    $sla = SlaPolicy::factory()->create(['tenant_id' => $this->tenant->id]);

    $response = $this->postJson('/api/v1/helpdesk/escalation-rules', [
        'sla_policy_id' => $sla->id,
        'name' => 'L2 Escalation',
        'trigger_minutes' => 60,
        'action_type' => 'notify',
        'is_active' => true,
    ]);

    $response->assertCreated()
        ->assertJsonPath('name', 'L2 Escalation');

    $this->assertDatabaseHas('escalation_rules', [
        'name' => 'L2 Escalation',
        'tenant_id' => $this->tenant->id,
    ]);
});

it('can show an escalation rule', function () {
    $rule = EscalationRule::factory()->create(['tenant_id' => $this->tenant->id]);

    $response = $this->getJson("/api/v1/helpdesk/escalation-rules/{$rule->id}");

    $response->assertOk()
        ->assertJsonPath('id', $rule->id)
        ->assertJsonPath('name', $rule->name);
});

it('can update an escalation rule', function () {
    $rule = EscalationRule::factory()->create(['tenant_id' => $this->tenant->id]);

    $response = $this->putJson("/api/v1/helpdesk/escalation-rules/{$rule->id}", [
        'trigger_minutes' => 120,
    ]);

    $response->assertOk()
        ->assertJsonPath('trigger_minutes', 120);

    $this->assertDatabaseHas('escalation_rules', [
        'id' => $rule->id,
        'trigger_minutes' => 120,
    ]);
});

it('can delete an escalation rule', function () {
    $rule = EscalationRule::factory()->create(['tenant_id' => $this->tenant->id]);

    $response = $this->deleteJson("/api/v1/helpdesk/escalation-rules/{$rule->id}");

    $response->assertNoContent();
    $this->assertDatabaseMissing('escalation_rules', ['id' => $rule->id]);
});

it('fails validation when required fields are missing on create', function () {
    $response = $this->postJson('/api/v1/helpdesk/escalation-rules', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['sla_policy_id', 'name', 'trigger_minutes', 'action_type']);
});

it('fails validation with invalid action_type', function () {
    $sla = SlaPolicy::factory()->create(['tenant_id' => $this->tenant->id]);

    $response = $this->postJson('/api/v1/helpdesk/escalation-rules', [
        'sla_policy_id' => $sla->id,
        'name' => 'Test Rule',
        'trigger_minutes' => 30,
        'action_type' => 'invalid_action',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['action_type']);
});

it('cannot access escalation rule from another tenant', function () {
    $otherTenant = Tenant::factory()->create();
    $rule = EscalationRule::factory()->create(['tenant_id' => $otherTenant->id]);

    $response = $this->getJson("/api/v1/helpdesk/escalation-rules/{$rule->id}");

    $response->assertNotFound();
});

it('only lists escalation rules from own tenant', function () {
    EscalationRule::factory()->count(2)->create(['tenant_id' => $this->tenant->id]);
    $otherTenant = Tenant::factory()->create();
    EscalationRule::factory()->count(3)->create(['tenant_id' => $otherTenant->id]);

    $response = $this->getJson('/api/v1/helpdesk/escalation-rules');

    $response->assertOk()
        ->assertJsonCount(2, 'data');
});

it('assigns tenant_id automatically on create', function () {
    $sla = SlaPolicy::factory()->create(['tenant_id' => $this->tenant->id]);

    $response = $this->postJson('/api/v1/helpdesk/escalation-rules', [
        'sla_policy_id' => $sla->id,
        'name' => 'Auto Tenant Rule',
        'trigger_minutes' => 45,
        'action_type' => 'reassign',
    ]);

    $response->assertCreated();

    $this->assertDatabaseHas('escalation_rules', [
        'name' => 'Auto Tenant Rule',
        'tenant_id' => $this->tenant->id,
    ]);
});

it('returns paginated structure with custom per_page', function () {
    EscalationRule::factory()->count(5)->create(['tenant_id' => $this->tenant->id]);

    $response = $this->getJson('/api/v1/helpdesk/escalation-rules?per_page=2');

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.per_page', 2)
        ->assertJsonPath('meta.total', 5);
});

it('eager loads slaPolicy relationship on show', function () {
    $rule = EscalationRule::factory()->create(['tenant_id' => $this->tenant->id]);

    $response = $this->getJson("/api/v1/helpdesk/escalation-rules/{$rule->id}");

    $response->assertOk()
        ->assertJsonStructure(['sla_policy']);
});
