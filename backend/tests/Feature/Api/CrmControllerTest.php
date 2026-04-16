<?php

namespace Tests\Feature\Api;

use App\Http\Middleware\CheckPermission;
use App\Models\CrmDeal;
use App\Models\CrmPipeline;
use App\Models\CrmPipelineStage;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class CrmControllerTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private Customer $customer;

    private CrmPipeline $pipeline;

    private CrmPipelineStage $stage;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);
        $this->withoutMiddleware([CheckPermission::class]);
        Event::fake();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);
        app()->instance('current_tenant_id', $this->tenant->id);
        setPermissionsTeamId($this->tenant->id);
        $this->user->assignRole('admin');
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->pipeline = CrmPipeline::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->stage = CrmPipelineStage::factory()->create([
            'tenant_id' => $this->tenant->id,
            'pipeline_id' => $this->pipeline->id,
        ]);

    }

    public function test_index_deals(): void
    {
        CrmDeal::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'pipeline_id' => $this->pipeline->id,
            'stage_id' => $this->stage->id,
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/v1/crm/deals');
        $response->assertOk();
    }

    public function test_index_deals_rejects_invalid_filters(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/crm/deals?per_page=abc&status=invalid');

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page', 'status']);
    }

    public function test_store_deal(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/crm/deals', [
            'customer_id' => $this->customer->id,
            'pipeline_id' => $this->pipeline->id,
            'stage_id' => $this->stage->id,
            'title' => 'Contrato calibração',
            'value' => '50000.00',
        ]);

        $response->assertCreated();
    }

    public function test_show_deal(): void
    {
        $deal = CrmDeal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'pipeline_id' => $this->pipeline->id,
            'stage_id' => $this->stage->id,
        ]);

        $response = $this->actingAs($this->user)->getJson("/api/v1/crm/deals/{$deal->id}");
        $response->assertOk();
    }

    public function test_update_deal(): void
    {
        $deal = CrmDeal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'pipeline_id' => $this->pipeline->id,
            'stage_id' => $this->stage->id,
        ]);

        $response = $this->actingAs($this->user)->putJson("/api/v1/crm/deals/{$deal->id}", [
            'title' => 'Atualizado',
            'value' => '75000.00',
        ]);

        $response->assertOk();
    }

    public function test_move_deal_to_stage(): void
    {
        $deal = CrmDeal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'pipeline_id' => $this->pipeline->id,
            'stage_id' => $this->stage->id,
            'status' => CrmDeal::STATUS_OPEN,
        ]);

        $stage2 = CrmPipelineStage::factory()->create([
            'tenant_id' => $this->tenant->id,
            'pipeline_id' => $this->pipeline->id,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/crm/deals/{$deal->id}/move", [
                'stage_id' => $stage2->id,
            ]);

        $response->assertOk();
    }

    public function test_win_deal(): void
    {
        $deal = CrmDeal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'pipeline_id' => $this->pipeline->id,
            'stage_id' => $this->stage->id,
            'status' => CrmDeal::STATUS_OPEN,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/crm/deals/{$deal->id}/win");

        $response->assertOk();
    }

    public function test_lose_deal(): void
    {
        $deal = CrmDeal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'pipeline_id' => $this->pipeline->id,
            'stage_id' => $this->stage->id,
            'status' => CrmDeal::STATUS_OPEN,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/crm/deals/{$deal->id}/lose", [
                'reason' => 'Preço alto',
            ]);

        $response->assertOk();
    }

    public function test_unauthenticated_returns_401(): void
    {
        $response = $this->getJson('/api/v1/crm/deals');
        $response->assertUnauthorized();
    }

    public function test_index_pipelines(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/v1/crm/pipelines');
        $response->assertOk();
    }

    public function test_tenant_isolation_deals(): void
    {
        CrmDeal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'pipeline_id' => $this->pipeline->id,
            'stage_id' => $this->stage->id,
        ]);

        $other = Tenant::factory()->create();
        $otherUser = User::factory()->create(['tenant_id' => $other->id, 'current_tenant_id' => $other->id]);
        $otherUser->tenants()->attach($other->id, ['is_default' => true]);
        $otherUser->assignRole('admin');

        app()->instance('current_tenant_id', $this->tenant->id);
        $response = $this->actingAs($otherUser)->getJson('/api/v1/crm/deals');
        $this->assertEmpty($response->json('data'));
    }

    public function test_index_activities_rejects_invalid_filters(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/crm/activities?per_page=abc&type=invalid&pending=not-bool');

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page', 'type', 'pending']);
    }
}
