<?php

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\SlaPolicy;
use App\Models\Tenant;
use App\Models\TicketCategory;
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

it('can list ticket categories with pagination', function () {
    TicketCategory::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

    $response = $this->getJson('/api/v1/helpdesk/ticket-categories');

    $response->assertOk()
        ->assertJsonStructure([
            'data',
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ])
        ->assertJsonCount(3, 'data');
});

it('can create a ticket category', function () {
    $sla = SlaPolicy::factory()->create(['tenant_id' => $this->tenant->id]);

    $response = $this->postJson('/api/v1/helpdesk/ticket-categories', [
        'name' => 'Network Issues',
        'description' => 'All network related tickets',
        'is_active' => true,
        'sla_policy_id' => $sla->id,
        'default_priority' => 'high',
    ]);

    $response->assertCreated()
        ->assertJsonPath('name', 'Network Issues');

    $this->assertDatabaseHas('ticket_categories', [
        'name' => 'Network Issues',
        'tenant_id' => $this->tenant->id,
    ]);
});

it('can show a ticket category', function () {
    $category = TicketCategory::factory()->create(['tenant_id' => $this->tenant->id]);

    $response = $this->getJson("/api/v1/helpdesk/ticket-categories/{$category->id}");

    $response->assertOk()
        ->assertJsonPath('id', $category->id)
        ->assertJsonPath('name', $category->name);
});

it('can update a ticket category', function () {
    $category = TicketCategory::factory()->create(['tenant_id' => $this->tenant->id]);

    $response = $this->putJson("/api/v1/helpdesk/ticket-categories/{$category->id}", [
        'name' => 'Updated Category',
    ]);

    $response->assertOk()
        ->assertJsonPath('name', 'Updated Category');

    $this->assertDatabaseHas('ticket_categories', [
        'id' => $category->id,
        'name' => 'Updated Category',
    ]);
});

it('can delete a ticket category', function () {
    $category = TicketCategory::factory()->create(['tenant_id' => $this->tenant->id]);

    $response = $this->deleteJson("/api/v1/helpdesk/ticket-categories/{$category->id}");

    $response->assertNoContent();
    $this->assertDatabaseMissing('ticket_categories', ['id' => $category->id]);
});

it('fails validation when required fields are missing on create', function () {
    $response = $this->postJson('/api/v1/helpdesk/ticket-categories', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

it('fails validation with invalid default_priority', function () {
    $response = $this->postJson('/api/v1/helpdesk/ticket-categories', [
        'name' => 'Test',
        'default_priority' => 'invalid_priority',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['default_priority']);
});

it('cannot access ticket category from another tenant', function () {
    $otherTenant = Tenant::factory()->create();
    $category = TicketCategory::factory()->create(['tenant_id' => $otherTenant->id]);

    $response = $this->getJson("/api/v1/helpdesk/ticket-categories/{$category->id}");

    $response->assertNotFound();
});

it('only lists ticket categories from own tenant', function () {
    TicketCategory::factory()->count(2)->create(['tenant_id' => $this->tenant->id]);
    $otherTenant = Tenant::factory()->create();
    TicketCategory::factory()->count(3)->create(['tenant_id' => $otherTenant->id]);

    $response = $this->getJson('/api/v1/helpdesk/ticket-categories');

    $response->assertOk()
        ->assertJsonCount(2, 'data');
});

it('assigns tenant_id automatically on create', function () {
    $response = $this->postJson('/api/v1/helpdesk/ticket-categories', [
        'name' => 'Auto Tenant Category',
    ]);

    $response->assertCreated();

    $this->assertDatabaseHas('ticket_categories', [
        'name' => 'Auto Tenant Category',
        'tenant_id' => $this->tenant->id,
    ]);
});

it('returns paginated structure with custom per_page', function () {
    TicketCategory::factory()->count(5)->create(['tenant_id' => $this->tenant->id]);

    $response = $this->getJson('/api/v1/helpdesk/ticket-categories?per_page=2');

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.per_page', 2)
        ->assertJsonPath('meta.total', 5);
});

it('eager loads slaPolicy relationship on show', function () {
    $sla = SlaPolicy::factory()->create(['tenant_id' => $this->tenant->id]);
    $category = TicketCategory::factory()->create([
        'tenant_id' => $this->tenant->id,
        'sla_policy_id' => $sla->id,
    ]);

    $response = $this->getJson("/api/v1/helpdesk/ticket-categories/{$category->id}");

    $response->assertOk()
        ->assertJsonStructure(['sla_policy']);
});
