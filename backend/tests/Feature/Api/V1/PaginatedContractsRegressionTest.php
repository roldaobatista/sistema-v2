<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\AccountReceivable;
use App\Models\AutoAssignmentRule;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaginatedContractsRegressionTest extends TestCase
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
        $this->customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_portal_cliente_paginated_endpoints_return_meta_contract(): void
    {
        AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'amount' => 250.00,
            'amount_paid' => 0,
            'status' => 'pending',
            'due_date' => now()->addDays(5),
        ]);

        DB::table('knowledge_base_articles')->insert([
            'tenant_id' => $this->tenant->id,
            'title' => 'FAQ Teste',
            'content' => 'Conteudo publicado',
            'category' => 'geral',
            'published' => true,
            'sort_order' => 1,
            'created_by' => $this->user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('nps_surveys')->insert([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'score' => 9,
            'category' => 'promoter',
            'comment' => 'Muito bom',
            'created_at' => now(),
        ]);

        $this->getJson("/api/v1/portal/financial/{$this->customer->id}")
            ->assertOk()
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'per_page', 'total'],
            ]);

        $this->getJson('/api/v1/portal/knowledge-base')
            ->assertOk()
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'per_page', 'total'],
            ]);

        $this->getJson('/api/v1/portal/nps')
            ->assertOk()
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'per_page', 'total'],
            ]);
    }

    public function test_hr_epi_list_returns_paginated_contract(): void
    {
        DB::table('epi_records')->insert([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'epi_type' => 'Luva',
            'ca_number' => 'CA-123',
            'status' => 'active',
            'delivered_at' => now()->toDateString(),
            'expiry_date' => now()->addYear()->toDateString(),
            'quantity' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->getJson('/api/v1/hr-advanced/epi')
            ->assertOk()
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'per_page', 'total'],
            ]);
    }

    public function test_service_ops_auto_assign_rules_returns_paginated_contract(): void
    {
        AutoAssignmentRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Regra 1',
            'entity_type' => 'work_order',
            'strategy' => 'round_robin',
            'conditions' => ['priority' => 'normal'],
            'technician_ids' => [$this->user->id],
            'required_skills' => [],
            'priority' => 1,
            'is_active' => true,
        ]);

        $this->getJson('/api/v1/operational/service-ops/auto-assign/rules')
            ->assertOk()
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'per_page', 'total'],
            ]);
    }
}
