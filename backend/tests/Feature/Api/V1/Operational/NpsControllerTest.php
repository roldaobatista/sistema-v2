<?php

namespace Tests\Feature\Api\V1\Operational;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Customer;
use App\Models\NpsResponse;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NpsControllerTest extends TestCase
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
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    private function createWorkOrder(array $overrides = []): WorkOrder
    {
        return WorkOrder::factory()->create(array_merge([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ], $overrides));
    }

    private function createNpsResponse(int $score, ?int $workOrderId = null): NpsResponse
    {
        $wo = $workOrderId ?? $this->createWorkOrder()->id;

        return NpsResponse::create([
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $wo,
            'customer_id' => $this->customer->id,
            'score' => $score,
            'comment' => "Score {$score} comment",
        ]);
    }

    // ─── STORE ────────────────────────────────────────────────────────

    public function test_store_creates_nps_response(): void
    {
        $workOrder = $this->createWorkOrder();

        $response = $this->postJson('/api/v1/operational/nps', [
            'work_order_id' => $workOrder->id,
            'score' => 9,
            'comment' => 'Excelente atendimento',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('nps_responses', [
            'work_order_id' => $workOrder->id,
            'score' => 9,
            'comment' => 'Excelente atendimento',
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
    }

    public function test_store_inherits_customer_from_work_order(): void
    {
        $workOrder = $this->createWorkOrder();

        $response = $this->postJson('/api/v1/operational/nps', [
            'work_order_id' => $workOrder->id,
            'score' => 7,
        ]);

        $response->assertStatus(201);
        $this->assertEquals($this->customer->id, $response->json('data.customer_id'));
    }

    public function test_store_allows_null_comment(): void
    {
        $workOrder = $this->createWorkOrder();

        $response = $this->postJson('/api/v1/operational/nps', [
            'work_order_id' => $workOrder->id,
            'score' => 5,
        ]);

        $response->assertStatus(201);
        $this->assertNull($response->json('data.comment'));
    }

    public function test_store_validation_requires_work_order_id(): void
    {
        $response = $this->postJson('/api/v1/operational/nps', [
            'score' => 8,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['work_order_id']);
    }

    public function test_store_validation_requires_score(): void
    {
        $workOrder = $this->createWorkOrder();

        $response = $this->postJson('/api/v1/operational/nps', [
            'work_order_id' => $workOrder->id,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['score']);
    }

    public function test_store_validation_score_min_0_max_10(): void
    {
        $workOrder = $this->createWorkOrder();

        $responseLow = $this->postJson('/api/v1/operational/nps', [
            'work_order_id' => $workOrder->id,
            'score' => -1,
        ]);
        $responseLow->assertUnprocessable()
            ->assertJsonValidationErrors(['score']);

        $responseHigh = $this->postJson('/api/v1/operational/nps', [
            'work_order_id' => $workOrder->id,
            'score' => 11,
        ]);
        $responseHigh->assertUnprocessable()
            ->assertJsonValidationErrors(['score']);
    }

    public function test_store_validation_rejects_other_tenant_work_order(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherWO = WorkOrder::factory()->create([
            'tenant_id' => $otherTenant->id,
            'customer_id' => $otherCustomer->id,
        ]);

        $response = $this->postJson('/api/v1/operational/nps', [
            'work_order_id' => $otherWO->id,
            'score' => 8,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['work_order_id']);
    }

    public function test_store_validation_rejects_nonexistent_work_order(): void
    {
        $response = $this->postJson('/api/v1/operational/nps', [
            'work_order_id' => 999999,
            'score' => 5,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['work_order_id']);
    }

    // ─── STATS ────────────────────────────────────────────────────────

    public function test_stats_returns_zero_when_no_responses(): void
    {
        $response = $this->getJson('/api/v1/operational/nps/stats');

        $response->assertOk()
            ->assertJsonPath('data.nps', 0)
            ->assertJsonPath('data.total', 0)
            ->assertJsonPath('data.promoters', 0)
            ->assertJsonPath('data.passives', 0)
            ->assertJsonPath('data.detractors', 0);
    }

    public function test_stats_calculates_nps_correctly(): void
    {
        // 3 promoters (score 9, 10, 10)
        $this->createNpsResponse(9);
        $this->createNpsResponse(10);
        $this->createNpsResponse(10);

        // 2 passives (score 7, 8)
        $this->createNpsResponse(7);
        $this->createNpsResponse(8);

        // 1 detractor (score 3)
        $this->createNpsResponse(3);

        // NPS = ((3 - 1) / 6) * 100 = 33.3
        $response = $this->getJson('/api/v1/operational/nps/stats');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertEquals(6, $data['total']);
        $this->assertEquals(33.3, $data['nps']);
        $this->assertEquals(50.0, $data['promoters_pct']);
        $this->assertEquals(33.3, $data['passives_pct']);
        $this->assertEquals(16.7, $data['detractors_pct']);
    }

    public function test_stats_100_percent_promoters(): void
    {
        $this->createNpsResponse(9);
        $this->createNpsResponse(10);

        $response = $this->getJson('/api/v1/operational/nps/stats');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertEquals(100, $data['nps']);
        $this->assertEquals(100, $data['promoters_pct']);
        $this->assertEquals(0, $data['detractors_pct']);
    }

    public function test_stats_100_percent_detractors(): void
    {
        $this->createNpsResponse(0);
        $this->createNpsResponse(3);
        $this->createNpsResponse(6);

        $response = $this->getJson('/api/v1/operational/nps/stats');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertEquals(-100, $data['nps']);
        $this->assertEquals(100, $data['detractors_pct']);
    }

    public function test_stats_respects_tenant_isolation(): void
    {
        // Current tenant: 1 promoter
        $this->createNpsResponse(10);

        // Other tenant: 1 detractor
        $otherTenant = Tenant::factory()->create();
        $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherWO = WorkOrder::factory()->create([
            'tenant_id' => $otherTenant->id,
            'customer_id' => $otherCustomer->id,
        ]);
        NpsResponse::withoutGlobalScopes()->create([
            'tenant_id' => $otherTenant->id,
            'work_order_id' => $otherWO->id,
            'customer_id' => $otherCustomer->id,
            'score' => 1,
            'comment' => 'Other tenant',
        ]);

        $response = $this->getJson('/api/v1/operational/nps/stats');

        $response->assertOk();
        // Only our tenant's response should count
        $this->assertEquals(1, $response->json('data.total'));
        $this->assertEquals(100.0, $response->json('data.nps'));
    }

    // ─── AUTH ─────────────────────────────────────────────────────────

    public function test_unauthenticated_user_gets_401(): void
    {
        Sanctum::actingAs(new User, []);
        $this->app['auth']->forgetGuards();

        $workOrder = $this->createWorkOrder();

        $response = $this->postJson('/api/v1/operational/nps', [
            'work_order_id' => $workOrder->id,
            'score' => 8,
        ]);

        $response->assertUnauthorized();
    }
}
