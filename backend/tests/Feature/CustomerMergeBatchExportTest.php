<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\CrmActivity;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Customer Merge & Batch Export Tests - validates customer deduplication,
 * merge functionality, batch CSV export, and price history endpoints.
 */
class CustomerMergeBatchExportTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private Customer $customer;

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
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        app()->instance('current_tenant_id', $this->tenant->id);
        setPermissionsTeamId($this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_search_duplicates_by_name(): void
    {
        Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Duplicado Teste',
        ]);
        Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Duplicado Teste',
        ]);

        $response = $this->getJson('/api/v1/customers/search-duplicates?type=name');

        $response->assertOk()
            ->assertJsonPath('data.0.key', 'duplicado teste')
            ->assertJsonPath('data.0.count', 2);
    }

    public function test_search_duplicates_by_document(): void
    {
        // Wave 5 (DATA-007): UNIQUE composto (tenant_id, document_hash,
        // sentinela soft-delete) bloqueia duplicatas em NOVOS inserts. A
        // feature `search-duplicates` segue válida para detectar registros
        // LEGADOS criados antes do UNIQUE entrar em vigor.
        //
        // Simulamos o cenário legado inserindo DOIS customers com o mesmo
        // `document_hash` mas em estados de soft-delete diferentes — assim
        // a UNIQUE composta não bloqueia (a sentinela difere) e o controller
        // detecta como duplicata por agrupar pelo hash. Em produção real
        // isso representaria registros importados de sistema legado ou
        // criados antes de Wave 1B.
        $hash = Customer::hashSearchable('12345678000190', digitsOnly: true);
        $now = now();
        // Insert 1: ativo (deleted_at NULL → sentinela = epoch)
        DB::table('customers')->insert([
            'tenant_id' => $this->tenant->id,
            'type' => 'PJ',
            'name' => 'Cliente Doc Legacy A',
            'document' => encrypt('12.345.678/0001-90'),
            'document_hash' => $hash,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
        // Insert 2: soft-deleted artificial — escapa do UNIQUE (sentinela
        // distinta) mas mantém mesmo `document_hash` para o agrupamento.
        DB::table('customers')->insert([
            'tenant_id' => $this->tenant->id,
            'type' => 'PJ',
            'name' => 'Cliente Doc Legacy B',
            'document' => encrypt('12345678000190'),
            'document_hash' => $hash,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => $now,
        ]);

        $response = $this->getJson('/api/v1/customers/search-duplicates?type=document');

        $response->assertOk()
            ->assertJsonPath('data.0.count', 2)
            ->assertJsonPath('data.0.customers.0.id', fn ($id) => is_int($id));
    }

    public function test_search_duplicates_validates_type(): void
    {
        $response = $this->getJson('/api/v1/customers/search-duplicates?type=invalid');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    }

    public function test_merge_customers_transfers_relationships(): void
    {
        $primary = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $duplicate = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $duplicate->id,
        ]);

        $response = $this->postJson('/api/v1/customers/merge', [
            'primary_id' => $primary->id,
            'duplicate_ids' => [$duplicate->id],
        ]);

        $response->assertOk();

        $this->assertSoftDeleted('customers', ['id' => $duplicate->id]);
        $this->assertNotNull(WorkOrder::where('customer_id', $primary->id)->first());
    }

    public function test_merge_rejects_same_primary_and_duplicate(): void
    {
        $response = $this->postJson('/api/v1/customers/merge', [
            'primary_id' => $this->customer->id,
            'duplicate_ids' => [$this->customer->id],
        ]);

        $response->assertStatus(422);
    }

    public function test_merge_logs_system_activity_and_appends_notes_without_null_prefix(): void
    {
        $primary = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'notes' => null,
        ]);
        $duplicate = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Cliente Duplicado',
            'notes' => 'Observacao importada',
        ]);

        $response = $this->postJson('/api/v1/customers/merge', [
            'primary_id' => $primary->id,
            'duplicate_ids' => [$duplicate->id],
        ]);

        $response->assertOk();

        $primary->refresh();

        $this->assertStringContainsString('Observacao importada', (string) $primary->notes);
        $this->assertStringNotContainsString('null', strtolower((string) $primary->notes));
        $this->assertDatabaseHas('crm_activities', [
            'tenant_id' => $this->tenant->id,
            'customer_id' => $primary->id,
            'type' => CrmActivity::TYPE_SYSTEM,
            'title' => 'Fusao de clientes concluida',
        ]);
    }

    public function test_search_duplicates_ignores_other_tenant_records(): void
    {
        $otherTenant = Tenant::factory()->create();

        Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Cliente Isolado',
        ]);
        Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Cliente Isolado',
        ]);
        Customer::factory()->create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Cliente Isolado',
        ]);
        Customer::factory()->create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Cliente Isolado',
        ]);

        $response = $this->getJson('/api/v1/customers/search-duplicates?type=name');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.count', 2);
    }

    public function test_batch_export_entities_lists_available(): void
    {
        $response = $this->getJson('/api/v1/batch-export/entities');

        $response->assertOk();

        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertGreaterThanOrEqual(1, count($data));

        $entity = $data[0];
        $this->assertArrayHasKey('key', $entity);
        $this->assertArrayHasKey('label', $entity);
        $this->assertArrayHasKey('fields', $entity);
        $this->assertArrayHasKey('count', $entity);
    }

    public function test_batch_export_csv_for_customers(): void
    {
        $response = $this->postJson('/api/v1/batch-export/csv', [
            'entity' => 'customers',
        ]);

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
    }

    public function test_batch_export_csv_validates_entity(): void
    {
        $response = $this->postJson('/api/v1/batch-export/csv', [
            'entity' => 'invalid_entity',
        ]);

        $response->assertStatus(422);
    }

    public function test_batch_print_validates_entity_and_ids(): void
    {
        $workOrders = WorkOrder::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $response = $this->postJson('/api/v1/batch-export/print', [
            'entity' => 'work_orders',
            'ids' => $workOrders->pluck('id')->all(),
        ]);

        $response->assertOk();
        $this->assertEquals('work_orders', $response->json('data.entity'));
    }

    public function test_price_history_index(): void
    {
        $response = $this->getJson('/api/v1/price-history');

        $response->assertOk();
    }

    public function test_price_history_with_date_filter(): void
    {
        $response = $this->getJson('/api/v1/price-history?date_from=2025-01-01&date_to=2025-12-31');

        $response->assertOk();
    }
}
