<?php

namespace Tests\Unit;

use App\Models\AccountReceivable;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\Quote;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Model Relationship Tests — validates that Eloquent relationships
 * resolve to the EXPECTED class, belong to the EXPECTED tenant, and
 * return the EXPECTED record IDs (not "at least one something").
 *
 * Anti-pattern guard (qa-01/02/06/07 — re-auditoria Camada 1 r3):
 *  - PROIBIDO `assertGreaterThanOrEqual(1, count())` — passa mesmo se a
 *    relação não estiver declarada no model.
 *  - PROIBIDO `assertNotNull($relation)` isolado — não valida id/tenant.
 *  - PROIBIDO teste tautológico que re-afirma o que o setUp já setou.
 *  - Cada teste compara IDs específicos criados no caso.
 */
class ModelRelationshipTest extends TestCase
{
    private Tenant $tenant;

    private Tenant $otherTenant;

    private User $user;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();

        $this->tenant = Tenant::factory()->create();
        $this->otherTenant = Tenant::factory()->create();

        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);

        $this->customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        app()->instance('current_tenant_id', $this->tenant->id);
        $this->actingAs($this->user);
    }

    // ── CUSTOMER → HAS MANY ─────────────────────────────────────────────

    public function test_customer_work_orders_relation_returns_only_records_of_that_customer(): void
    {
        $mine = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $otherCustomer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $otherCustomer->id,
        ]);

        $this->assertInstanceOf(HasMany::class, $this->customer->workOrders());

        /** @var EloquentCollection<int, WorkOrder> $workOrders */
        $workOrders = $this->customer->workOrders()->get();

        $this->assertCount(1, $workOrders, 'customer.workOrders() deve retornar apenas WOs do próprio customer');
        $this->assertSame($mine->id, $workOrders->first()->id);
        $this->assertSame($this->customer->id, $workOrders->first()->customer_id);
        $this->assertSame($this->tenant->id, $workOrders->first()->tenant_id);
    }

    public function test_customer_equipments_relation_returns_only_records_of_that_customer(): void
    {
        $mine = Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $otherCustomer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $otherCustomer->id,
        ]);

        $this->assertInstanceOf(HasMany::class, $this->customer->equipments());

        $equipments = $this->customer->equipments()->get();

        $this->assertCount(1, $equipments);
        $this->assertSame($mine->id, $equipments->first()->id);
        $this->assertSame($this->customer->id, $equipments->first()->customer_id);
    }

    public function test_customer_quotes_relation_returns_only_records_of_that_customer(): void
    {
        $mine = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $otherCustomer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $otherCustomer->id,
        ]);

        $this->assertInstanceOf(HasMany::class, $this->customer->quotes());

        $quotes = $this->customer->quotes()->get();

        $this->assertCount(1, $quotes);
        $this->assertSame($mine->id, $quotes->first()->id);
        $this->assertSame($this->customer->id, $quotes->first()->customer_id);
    }

    public function test_customer_belongs_to_correct_tenant(): void
    {
        $this->assertSame($this->tenant->id, $this->customer->tenant_id);
        $this->assertNotSame($this->otherTenant->id, $this->customer->tenant_id);
    }

    // ── WORK ORDER → BELONGS TO ─────────────────────────────────────────

    public function test_work_order_customer_relation_resolves_to_exact_customer_record(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $this->assertInstanceOf(BelongsTo::class, $wo->customer());

        $loaded = $wo->customer;

        $this->assertInstanceOf(Customer::class, $loaded);
        $this->assertSame($this->customer->id, $loaded->id);
        $this->assertSame($this->tenant->id, $loaded->tenant_id);
    }

    public function test_work_order_tenant_id_matches_its_customer_tenant_id(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $this->assertSame($this->tenant->id, $wo->tenant_id);
        $this->assertSame($wo->customer->tenant_id, $wo->tenant_id);
    }

    // ── EQUIPMENT → BELONGS TO ──────────────────────────────────────────

    public function test_equipment_customer_relation_resolves_to_exact_customer_record(): void
    {
        $eq = Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $this->assertInstanceOf(BelongsTo::class, $eq->customer());

        $loaded = $eq->customer;

        $this->assertInstanceOf(Customer::class, $loaded);
        $this->assertSame($this->customer->id, $loaded->id);
        $this->assertSame($this->tenant->id, $loaded->tenant_id);
    }

    // ── QUOTE → BELONGS TO ──────────────────────────────────────────────

    public function test_quote_customer_relation_resolves_to_exact_customer_record(): void
    {
        $quote = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $this->assertInstanceOf(BelongsTo::class, $quote->customer());

        $loaded = $quote->customer;

        $this->assertInstanceOf(Customer::class, $loaded);
        $this->assertSame($this->customer->id, $loaded->id);
        $this->assertSame($this->tenant->id, $loaded->tenant_id);
    }

    // ── USER → BELONGS TO MANY TENANTS ──────────────────────────────────

    public function test_user_tenants_relation_includes_the_exact_attached_tenant(): void
    {
        $this->assertInstanceOf(BelongsToMany::class, $this->user->tenants());

        $tenants = $this->user->tenants()->get();

        $this->assertCount(1, $tenants, 'user should have exactly the one tenant attached in setUp');
        $this->assertSame($this->tenant->id, $tenants->first()->id);
        $this->assertTrue(
            (bool) $tenants->first()->pivot->is_default,
            'pivot is_default deve estar true conforme attach do setUp'
        );
    }

    public function test_user_tenants_relation_does_not_leak_other_tenants(): void
    {
        // otherTenant existe no banco mas NÃO foi attached ao user
        $attachedIds = $this->user->tenants()->pluck('tenants.id')->all();

        $this->assertContains($this->tenant->id, $attachedIds);
        $this->assertNotContains($this->otherTenant->id, $attachedIds);
    }

    // ── TENANT ESCOPO (substitui teste tautológico) ─────────────────────

    public function test_customer_global_scope_returns_only_records_of_current_tenant(): void
    {
        // Cliente do OUTRO tenant — não deve aparecer em queries padrão.
        // Global scope é registrado via addGlobalScope('tenant', closure) em BelongsToTenant,
        // então ignoramos usando o nome string.
        Customer::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->otherTenant->id,
            'name' => 'Outsider Customer',
        ]);

        $ids = Customer::query()->pluck('tenant_id')->unique()->values()->all();

        $this->assertSame([$this->tenant->id], $ids, 'global scope deve restringir ao current_tenant_id');
    }

    // ── ACCOUNT RECEIVABLE → BELONGS TO ─────────────────────────────────

    public function test_receivable_customer_relation_resolves_to_exact_customer_record(): void
    {
        $ar = AccountReceivable::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'description' => 'Test',
            'amount' => 100,
            'amount_paid' => 0,
            'status' => 'pending',
            'due_date' => now()->addDays(30),
        ]);

        $this->assertInstanceOf(BelongsTo::class, $ar->customer());

        $loaded = $ar->customer;

        $this->assertInstanceOf(Customer::class, $loaded);
        $this->assertSame($this->customer->id, $loaded->id);
        $this->assertSame($this->tenant->id, $loaded->tenant_id);
    }

    // ── SOFT DELETE BEHAVIOR ────────────────────────────────────────────

    public function test_soft_deleted_customer_is_hidden_from_default_query_and_visible_with_trashed(): void
    {
        $baseline = Customer::query()->count();

        $softTarget = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'SoftDelete Target',
        ]);
        $targetId = $softTarget->id;

        $this->assertSame($baseline + 1, Customer::query()->count(), 'registro recém-criado deve aparecer');

        $softTarget->delete();

        // Default query: registro some
        $this->assertSame($baseline, Customer::query()->count());
        $this->assertNull(Customer::query()->find($targetId));

        // withTrashed: registro volta, com o MESMO id e deleted_at preenchido
        $withTrashed = Customer::withTrashed()->find($targetId);
        $this->assertInstanceOf(Customer::class, $withTrashed);
        $this->assertSame($targetId, $withTrashed->id);
        $this->assertNotNull($withTrashed->deleted_at, 'deleted_at deve estar preenchido após soft delete');
        $this->assertSame($this->tenant->id, $withTrashed->tenant_id);

        // onlyTrashed: só o registro soft-deletado aparece
        $onlyTrashed = Customer::onlyTrashed()->pluck('id')->all();
        $this->assertContains($targetId, $onlyTrashed);
    }
}
