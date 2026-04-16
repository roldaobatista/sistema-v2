<?php

namespace Tests\Feature\Api\V1\RepairSeal;

use App\Events\RepairSeal\SealBatchReceived;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\InmetroSeal;
use App\Models\RepairSealBatch;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RepairSealBatchControllerTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);
        $this->withoutMiddleware([
            EnsureTenantScope::class,
            CheckPermission::class,
        ]);
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_index_returns_batches(): void
    {
        RepairSealBatch::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'received_by' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/repair-seal-batches');

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_store_creates_batch_with_individual_seals(): void
    {
        Event::fake();

        $response = $this->postJson('/api/v1/repair-seal-batches', [
            'type' => 'seal_reparo',
            'batch_code' => 'LOTE-TEST-001',
            'range_start' => '1',
            'range_end' => '10',
            'prefix' => 'RS-',
            'received_at' => now()->format('Y-m-d'),
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('repair_seal_batches', [
            'batch_code' => 'LOTE-TEST-001',
            'type' => 'seal_reparo',
            'quantity' => 10,
            'quantity_available' => 10,
        ]);

        // Verify individual seals created
        $batch = RepairSealBatch::where('batch_code', 'LOTE-TEST-001')->first();
        $this->assertEquals(10, InmetroSeal::where('batch_id', $batch->id)->count());

        // Check first and last seal numbers
        $this->assertDatabaseHas('inmetro_seals', [
            'batch_id' => $batch->id,
            'number' => 'RS-01',
            'status' => 'available',
        ]);
        $this->assertDatabaseHas('inmetro_seals', [
            'batch_id' => $batch->id,
            'number' => 'RS-10',
        ]);

        Event::assertDispatched(SealBatchReceived::class);
    }

    public function test_store_rejects_duplicate_batch_code(): void
    {
        RepairSealBatch::factory()->create([
            'tenant_id' => $this->tenant->id,
            'batch_code' => 'LOTE-DUP',
            'received_by' => $this->user->id,
        ]);

        $response = $this->postJson('/api/v1/repair-seal-batches', [
            'type' => 'seal',
            'batch_code' => 'LOTE-DUP',
            'range_start' => '1',
            'range_end' => '5',
            'received_at' => now()->format('Y-m-d'),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('batch_code');
    }

    public function test_show_returns_batch_with_seals(): void
    {
        $batch = RepairSealBatch::factory()->create([
            'tenant_id' => $this->tenant->id,
            'received_by' => $this->user->id,
        ]);
        InmetroSeal::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'batch_id' => $batch->id,
        ]);

        $response = $this->getJson("/api/v1/repair-seal-batches/{$batch->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $batch->id)
            ->assertJsonCount(3, 'data.seals');
    }

    public function test_cannot_access_batch_from_another_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'current_tenant_id' => $otherTenant->id,
        ]);
        $batch = RepairSealBatch::factory()->create([
            'tenant_id' => $otherTenant->id,
            'received_by' => $otherUser->id,
        ]);

        $response = $this->getJson("/api/v1/repair-seal-batches/{$batch->id}");

        $response->assertNotFound();
    }

    public function test_only_lists_batches_from_own_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'current_tenant_id' => $otherTenant->id,
        ]);
        RepairSealBatch::factory()->count(2)->create([
            'tenant_id' => $otherTenant->id,
            'received_by' => $otherUser->id,
        ]);
        RepairSealBatch::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'received_by' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/repair-seal-batches');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_store_fails_with_missing_required_fields(): void
    {
        $response = $this->postJson('/api/v1/repair-seal-batches', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['type', 'batch_code', 'range_start', 'range_end', 'received_at']);
    }

    public function test_index_returns_paginated_structure(): void
    {
        RepairSealBatch::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'received_by' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/repair-seal-batches');

        $response->assertOk()
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_store_assigns_tenant_id_automatically(): void
    {
        Event::fake();

        $response = $this->postJson('/api/v1/repair-seal-batches', [
            'type' => 'seal',
            'batch_code' => 'LOTE-AUTO-001',
            'range_start' => '1',
            'range_end' => '5',
            'received_at' => now()->format('Y-m-d'),
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('repair_seal_batches', [
            'batch_code' => 'LOTE-AUTO-001',
            'tenant_id' => $this->tenant->id,
            'received_by' => $this->user->id,
        ]);
    }
}
