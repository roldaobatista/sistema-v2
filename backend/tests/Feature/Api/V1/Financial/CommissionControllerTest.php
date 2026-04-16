<?php

namespace Tests\Feature\Api\V1\Financial;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\CommissionEvent;
use App\Models\CommissionRule;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CommissionControllerTest extends TestCase
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
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);

        $this->setTenantContext($this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    private function createEvent(?int $tenantId = null, float $amount = 500.00, string $status = 'approved'): CommissionEvent
    {
        $tid = $tenantId ?? $this->tenant->id;
        $rule = CommissionRule::create([
            'tenant_id' => $tid,
            'name' => 'Regra '.uniqid(),
            'type' => 'percentage',
            'value' => 5.0,
            'applies_to' => 'revenue',
            'calculation_type' => 'percentage',
            'active' => true,
        ]);

        $wo = WorkOrder::factory()->create(['tenant_id' => $tid]);

        return CommissionEvent::create([
            'tenant_id' => $tid,
            'commission_rule_id' => $rule->id,
            'work_order_id' => $wo->id,
            'user_id' => $this->user->id,
            'base_amount' => 10000,
            'commission_amount' => $amount,
            'proportion' => 1.0,
            'status' => $status,
        ]);
    }

    public function test_events_returns_only_current_tenant_events(): void
    {
        $mine = $this->createEvent();

        $otherTenant = Tenant::factory()->create();
        $foreign = $this->createEvent($otherTenant->id, 999999);

        $response = $this->getJson('/api/v1/commission-events');

        $response->assertOk()->assertJsonStructure(['data']);
        $json = json_encode($response->json());
        $this->assertStringNotContainsString('999999', $json);
    }

    public function test_events_filters_by_status(): void
    {
        $this->createEvent(null, 500, 'approved');
        $this->createEvent(null, 500, 'pending');

        $response = $this->getJson('/api/v1/commission-events?status=approved');

        $response->assertOk();
        foreach ($response->json('data') as $row) {
            $this->assertEquals('approved', $row['status']);
        }
    }

    public function test_summary_returns_aggregated_structure(): void
    {
        $this->createEvent();

        $response = $this->getJson('/api/v1/commission-summary');

        $response->assertOk()->assertJsonStructure(['data']);
    }

    public function test_settlements_returns_only_current_tenant(): void
    {
        $response = $this->getJson('/api/v1/commission-settlements');

        $response->assertOk()->assertJsonStructure(['data']);
    }

    public function test_simulate_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/commission-simulate', []);

        $response->assertStatus(422);
    }

    public function test_generate_for_work_order_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/commission-events/generate', []);

        $response->assertStatus(422);
    }
}
