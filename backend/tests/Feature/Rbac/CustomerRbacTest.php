<?php

use App\Http\Middleware\EnsureTenantScope;
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
});

function customerUser(Tenant $tenant, array $permissions = []): User
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
// Customers - View
// ============================================================

test('user WITH cadastros.customer.view can list customers', function () {
    $user = customerUser($this->tenant, ['cadastros.customer.view']);
    Sanctum::actingAs($user, ['*']);

    Customer::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->getJson('/api/v1/customers')->assertOk();
});

test('user WITHOUT cadastros.customer.view gets 403 on list customers', function () {
    $user = customerUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/customers')->assertForbidden();
});

test('user WITH cadastros.customer.view can show a customer', function () {
    $user = customerUser($this->tenant, ['cadastros.customer.view']);
    Sanctum::actingAs($user, ['*']);

    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->getJson("/api/v1/customers/{$customer->id}")->assertOk();
});

test('user WITHOUT cadastros.customer.view gets 403 on show customer', function () {
    $user = customerUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->getJson("/api/v1/customers/{$customer->id}")->assertForbidden();
});

test('user WITH cadastros.customer.view can access customer options', function () {
    $user = customerUser($this->tenant, ['cadastros.customer.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/customers/options')->assertOk();
});

test('user WITHOUT cadastros.customer.view gets 403 on customer options', function () {
    $user = customerUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/customers/options')->assertForbidden();
});

test('user WITH cadastros.customer.view can search for duplicates', function () {
    $user = customerUser($this->tenant, ['cadastros.customer.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/customers/duplicates')->assertOk();
});

test('user WITHOUT cadastros.customer.view gets 403 on duplicates search', function () {
    $user = customerUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/customers/duplicates')->assertForbidden();
});

// ============================================================
// Customers - Create
// ============================================================

test('user WITH cadastros.customer.create can store customer', function () {
    $user = customerUser($this->tenant, ['cadastros.customer.create']);
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/customers', [
        'name' => 'Cliente Teste LTDA',
        'type' => 'PJ',
        'email' => 'contato@clienteteste.com',
    ])->assertStatus(201);
});

test('user WITHOUT cadastros.customer.create gets 403 on store customer', function () {
    $user = customerUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/customers', [
        'name' => 'Cliente Teste LTDA',
        'type' => 'PJ',
    ])->assertForbidden();
});

// ============================================================
// Customers - Update
// ============================================================

test('user WITH cadastros.customer.update can update customer', function () {
    $user = customerUser($this->tenant, ['cadastros.customer.update']);
    Sanctum::actingAs($user, ['*']);

    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->putJson("/api/v1/customers/{$customer->id}", [
        'name' => 'Cliente Atualizado',
    ])->assertOk();
});

test('user WITHOUT cadastros.customer.update gets 403 on update customer', function () {
    $user = customerUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->putJson("/api/v1/customers/{$customer->id}", [
        'name' => 'Cliente Atualizado',
    ])->assertForbidden();
});

test('user WITH cadastros.customer.update can merge customers', function () {
    $user = customerUser($this->tenant, ['cadastros.customer.update']);
    Sanctum::actingAs($user, ['*']);

    $customer1 = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $customer2 = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->postJson('/api/v1/customers/merge', [
        'primary_id' => $customer1->id,
        'duplicate_ids' => [$customer2->id],
    ])->assertOk();
});

test('user WITHOUT cadastros.customer.update gets 403 on merge customers', function () {
    $user = customerUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $customer1 = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $customer2 = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->postJson('/api/v1/customers/merge', [
        'primary_id' => $customer1->id,
        'duplicate_ids' => [$customer2->id],
    ])->assertForbidden();
});

// ============================================================
// Customers - Delete
// ============================================================

test('user WITH cadastros.customer.delete can delete customer', function () {
    $user = customerUser($this->tenant, ['cadastros.customer.delete']);
    Sanctum::actingAs($user, ['*']);

    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->deleteJson("/api/v1/customers/{$customer->id}")->assertNoContent();
});

test('user WITHOUT cadastros.customer.delete gets 403 on delete customer', function () {
    $user = customerUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->deleteJson("/api/v1/customers/{$customer->id}")->assertForbidden();
});

// ============================================================
// Customers - Batch Export
// ============================================================

test('user WITH cadastros.customer.view can access batch export entities', function () {
    $user = customerUser($this->tenant, ['cadastros.customer.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/batch-export/entities')->assertOk();
});

test('user WITHOUT cadastros.customer.view gets 403 on batch export', function () {
    $user = customerUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/batch-export/entities')->assertForbidden();
});

// ============================================================
// Cross-permission: view does not grant create
// ============================================================

test('user WITH only cadastros.customer.view cannot create customer', function () {
    $user = customerUser($this->tenant, ['cadastros.customer.view']);
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/customers', [
        'name' => 'Novo cliente',
        'type' => 'PF',
    ])->assertForbidden();
});

test('user WITH only cadastros.customer.view cannot delete customer', function () {
    $user = customerUser($this->tenant, ['cadastros.customer.view']);
    Sanctum::actingAs($user, ['*']);

    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->deleteJson("/api/v1/customers/{$customer->id}")->assertForbidden();
});

test('user WITH only cadastros.customer.create cannot delete customer', function () {
    $user = customerUser($this->tenant, ['cadastros.customer.create']);
    Sanctum::actingAs($user, ['*']);

    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->deleteJson("/api/v1/customers/{$customer->id}")->assertForbidden();
});
