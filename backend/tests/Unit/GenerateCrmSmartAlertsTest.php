<?php

namespace Tests\Unit;

use App\Jobs\GenerateCrmSmartAlerts;
use App\Models\Tenant;
use App\Services\Crm\CrmSmartAlertGenerator;
use Mockery;
use Tests\TestCase;

class GenerateCrmSmartAlertsTest extends TestCase
{
    public function test_job_generates_alerts_for_each_active_tenant(): void
    {
        $activeA = Tenant::factory()->create(['status' => Tenant::STATUS_ACTIVE]);
        $activeB = Tenant::factory()->create(['status' => Tenant::STATUS_ACTIVE]);
        Tenant::factory()->create(['status' => Tenant::STATUS_INACTIVE]);

        $generator = Mockery::mock(CrmSmartAlertGenerator::class);
        $generator->shouldReceive('generateForTenant')->once()->with($activeA->id);
        $generator->shouldReceive('generateForTenant')->once()->with($activeB->id);

        $this->app->instance(CrmSmartAlertGenerator::class, $generator);

        (new GenerateCrmSmartAlerts)->handle();

        $this->assertFalse(app()->bound('current_tenant_id'));
    }
}
