<?php

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\CrmPipeline;
use App\Models\CrmPipelineStage;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Gate::before(fn () => true);
    $this->withoutMiddleware([
        EnsureTenantScope::class,
        CheckPermission::class,
    ]);

    $this->tenant = Tenant::factory()->create();
    $this->user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'current_tenant_id' => $this->tenant->id,
        'is_active' => true,
    ]);
    app()->instance('current_tenant_id', $this->tenant->id);
    Sanctum::actingAs($this->user, ['*']);

    $this->customer = Customer::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);

    $this->pipeline = CrmPipeline::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);

    $this->stage = CrmPipelineStage::factory()->create([
        'tenant_id' => $this->tenant->id,
        'pipeline_id' => $this->pipeline->id,
    ]);
});

test('can create deal for same customer as existing deal', function () {
    // First deal
    $response1 = $this->postJson('/api/v1/crm/deals', [
        'customer_id' => $this->customer->id,
        'pipeline_id' => $this->pipeline->id,
        'stage_id' => $this->stage->id,
        'title' => 'First Deal',
        'value' => 5000.00,
    ]);

    $response1->assertStatus(201);

    // Second deal for same customer (should be allowed)
    $response2 = $this->postJson('/api/v1/crm/deals', [
        'customer_id' => $this->customer->id,
        'pipeline_id' => $this->pipeline->id,
        'stage_id' => $this->stage->id,
        'title' => 'Second Deal',
        'value' => 3000.00,
    ]);

    $response2->assertStatus(201);
});

test('duplicate customer document (CPF) within same tenant is rejected', function () {
    // Create first customer with a specific document
    $this->postJson('/api/v1/customers', [
        'type' => 'PF',
        'name' => 'Customer One',
        'document' => '52998224725', // Valid CPF
    ])->assertStatus(201);

    // Try to create second customer with same document
    $response = $this->postJson('/api/v1/customers', [
        'type' => 'PF',
        'name' => 'Customer Two',
        'document' => '52998224725',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['document']);
});

test('duplicate customer document (CNPJ) within same tenant is rejected', function () {
    $this->postJson('/api/v1/customers', [
        'type' => 'PJ',
        'name' => 'Company One',
        'document' => '11222333000181', // Valid CNPJ
    ])->assertStatus(201);

    $response = $this->postJson('/api/v1/customers', [
        'type' => 'PJ',
        'name' => 'Company Two',
        'document' => '11222333000181',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['document']);
});

test('same document is allowed for different tenants', function () {
    // Create customer in first tenant
    $this->postJson('/api/v1/customers', [
        'type' => 'PJ',
        'name' => 'Company Tenant 1',
        'document' => '11222333000181',
    ])->assertStatus(201);

    // Switch to second tenant
    $otherTenant = Tenant::factory()->create();
    $otherUser = User::factory()->create([
        'tenant_id' => $otherTenant->id,
        'current_tenant_id' => $otherTenant->id,
        'is_active' => true,
    ]);
    app()->instance('current_tenant_id', $otherTenant->id);
    Sanctum::actingAs($otherUser, ['*']);

    // Same document in different tenant should work
    $response = $this->postJson('/api/v1/customers', [
        'type' => 'PJ',
        'name' => 'Company Tenant 2',
        'document' => '11222333000181',
    ]);

    $response->assertStatus(201);
});

test('deal requires customer_id', function () {
    $response = $this->postJson('/api/v1/crm/deals', [
        'pipeline_id' => $this->pipeline->id,
        'stage_id' => $this->stage->id,
        'title' => 'Deal without customer',
        'value' => 1000.00,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['customer_id']);
});

test('deal requires pipeline_id', function () {
    $response = $this->postJson('/api/v1/crm/deals', [
        'customer_id' => $this->customer->id,
        'stage_id' => $this->stage->id,
        'title' => 'Deal without pipeline',
        'value' => 1000.00,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['pipeline_id']);
});
