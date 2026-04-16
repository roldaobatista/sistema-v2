<?php

namespace Tests\Feature\Api\V1\Financial;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\CommissionDispute;
use App\Models\CommissionEvent;
use App\Models\CommissionRule;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CommissionDisputeControllerTest extends TestCase
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

    private function createCommissionEvent(?int $tenantId = null, ?int $userId = null): CommissionEvent
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
            'user_id' => $userId ?? $this->user->id,
            'base_amount' => 10000,
            'commission_amount' => 500,
            'proportion' => 1.0,
            'status' => 'approved',
        ]);
    }

    public function test_index_returns_only_current_tenant_disputes(): void
    {
        $event = $this->createCommissionEvent();
        $mine = CommissionDispute::create([
            'tenant_id' => $this->tenant->id,
            'commission_event_id' => $event->id,
            'user_id' => $this->user->id,
            'reason' => 'Cálculo incorreto do rateio entre vendedores',
            'status' => 'open',
        ]);

        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherEvent = $this->createCommissionEvent($otherTenant->id, $otherUser->id);
        $foreign = CommissionDispute::create([
            'tenant_id' => $otherTenant->id,
            'commission_event_id' => $otherEvent->id,
            'user_id' => $otherUser->id,
            'reason' => 'LEAK motivo de outro tenant para não vazar',
            'status' => 'open',
        ]);

        $response = $this->getJson('/api/v1/commission-disputes');

        $response->assertOk()->assertJsonStructure(['data']);
        $json = json_encode($response->json());
        $this->assertStringNotContainsString('LEAK motivo', $json);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/commission-disputes', []);

        $response->assertStatus(422);
    }

    public function test_store_rejects_short_reason(): void
    {
        $event = $this->createCommissionEvent();

        $response = $this->postJson('/api/v1/commission-disputes', [
            'commission_event_id' => $event->id,
            'reason' => 'curto',
        ]);

        $response->assertStatus(422);
    }

    public function test_store_creates_dispute(): void
    {
        $event = $this->createCommissionEvent();

        $response = $this->postJson('/api/v1/commission-disputes', [
            'commission_event_id' => $event->id,
            'reason' => 'Discordo do valor apurado — rateio entre vendedores está incorreto',
        ]);

        $this->assertContains($response->status(), [200, 201]);
        $this->assertDatabaseHas('commission_disputes', [
            'tenant_id' => $this->tenant->id,
            'commission_event_id' => $event->id,
            'user_id' => $this->user->id,
        ]);
    }

    public function test_store_rejects_event_from_other_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $foreignEvent = $this->createCommissionEvent($otherTenant->id);

        $response = $this->postJson('/api/v1/commission-disputes', [
            'commission_event_id' => $foreignEvent->id,
            'reason' => 'Tentando disputar evento de outro tenant — deveria falhar',
        ]);

        $response->assertStatus(422);
    }

    public function test_show_returns_dispute_details(): void
    {
        $event = $this->createCommissionEvent();
        $dispute = CommissionDispute::create([
            'tenant_id' => $this->tenant->id,
            'commission_event_id' => $event->id,
            'user_id' => $this->user->id,
            'reason' => 'Motivo completo para a contestação da comissão',
            'status' => 'open',
        ]);

        $response = $this->getJson("/api/v1/commission-disputes/{$dispute->id}");

        $response->assertOk();
    }
}
