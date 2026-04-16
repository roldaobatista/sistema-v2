<?php

namespace Tests\Feature\TenantIsolation;

use App\Models\CrmDeal;
use App\Models\CrmPipeline;
use App\Models\CrmPipelineStage;

class CrmIsolationTest extends TenantIsolationTestCase
{
    private function createDealForTenant(int $tenantId): CrmDeal
    {
        app()->instance('current_tenant_id', $tenantId);

        $pipeline = CrmPipeline::factory()->create(['tenant_id' => $tenantId]);
        $stage = CrmPipelineStage::factory()->create([
            'tenant_id' => $tenantId,
            'pipeline_id' => $pipeline->id,
        ]);

        return CrmDeal::factory()->create([
            'tenant_id' => $tenantId,
            'pipeline_id' => $pipeline->id,
            'stage_id' => $stage->id,
        ]);
    }

    private function createPipelineForTenant(int $tenantId): CrmPipeline
    {
        app()->instance('current_tenant_id', $tenantId);

        return CrmPipeline::factory()->create(['tenant_id' => $tenantId]);
    }

    public function test_crm_deals_index_only_returns_own_tenant(): void
    {
        $dealA = $this->createDealForTenant($this->tenantA->id);
        $dealB = $this->createDealForTenant($this->tenantB->id);

        $this->actingAsTenantA();

        $response = $this->getJson('/api/v1/crm/deals');

        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($dealA->id, $ids);
        $this->assertNotContains($dealB->id, $ids);
    }

    public function test_cannot_access_other_tenant_crm_deal(): void
    {
        $dealB = $this->createDealForTenant($this->tenantB->id);

        $this->actingAsTenantA();

        $response = $this->getJson("/api/v1/crm/deals/{$dealB->id}");

        $response->assertNotFound();
    }

    public function test_cannot_update_other_tenant_crm_deal(): void
    {
        $dealB = $this->createDealForTenant($this->tenantB->id);

        $this->actingAsTenantA();

        $response = $this->putJson("/api/v1/crm/deals/{$dealB->id}", [
            'title' => 'Hijacked Deal',
        ]);

        $response->assertNotFound();
    }

    public function test_crm_pipelines_isolated_by_tenant(): void
    {
        $pipelineA = $this->createPipelineForTenant($this->tenantA->id);
        $pipelineB = $this->createPipelineForTenant($this->tenantB->id);

        $this->actingAsTenantA();

        $response = $this->getJson('/api/v1/crm/pipelines');

        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($pipelineA->id, $ids);
        $this->assertNotContains($pipelineB->id, $ids);
    }
}
