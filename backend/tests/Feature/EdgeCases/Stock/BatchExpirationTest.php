<?php

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
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

    $this->product = Product::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);

    $this->warehouse = Warehouse::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Batch Warehouse',
        'code' => 'WH-BATCH',
        'type' => Warehouse::TYPE_FIXED,
        'is_active' => true,
    ]);
});

test('batch with expiration date in the past is accepted on creation', function () {
    // StoreBatchRequest allows nullable expires_at with 'after_or_equal:manufacturing_date'
    $response = $this->postJson('/api/v1/batches', [
        'product_id' => $this->product->id,
        'batch_number' => 'BATCH-EXPIRED-001',
        'manufacturing_date' => now()->subMonths(12)->toDateString(),
        'expires_at' => now()->subDays(1)->toDateString(),
    ]);

    $response->assertSuccessful();
});

test('batch with today expiration date is accepted', function () {
    $response = $this->postJson('/api/v1/batches', [
        'product_id' => $this->product->id,
        'batch_number' => 'BATCH-TODAY-001',
        'manufacturing_date' => now()->subMonths(6)->toDateString(),
        'expires_at' => now()->toDateString(),
    ]);

    $response->assertSuccessful();
});

test('batch expiration date cannot be before manufacturing date', function () {
    $response = $this->postJson('/api/v1/batches', [
        'product_id' => $this->product->id,
        'batch_number' => 'BATCH-INVALID-DATE',
        'manufacturing_date' => now()->toDateString(),
        'expires_at' => now()->subDays(1)->toDateString(),
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['expires_at']);
});

test('batch with null expiration date is accepted', function () {
    $response = $this->postJson('/api/v1/batches', [
        'product_id' => $this->product->id,
        'batch_number' => 'BATCH-NO-EXPIRY-001',
    ]);

    $response->assertSuccessful();
});

test('duplicate batch number within same tenant is rejected', function () {
    $this->postJson('/api/v1/batches', [
        'product_id' => $this->product->id,
        'batch_number' => 'BATCH-UNIQUE-001',
    ])->assertSuccessful();

    $response = $this->postJson('/api/v1/batches', [
        'product_id' => $this->product->id,
        'batch_number' => 'BATCH-UNIQUE-001',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['batch_number']);
});

test('batch requires product_id', function () {
    $response = $this->postJson('/api/v1/batches', [
        'batch_number' => 'BATCH-NO-PRODUCT',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['product_id']);
});
