<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckPermission;
use App\Models\Lookups\AutomationReportFormat;
use App\Models\Lookups\AutomationReportFrequency;
use App\Models\Lookups\AutomationReportType;
use App\Models\ScheduledReport;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class AutomationLookupCompatibilityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);
        $this->withoutMiddleware(CheckPermission::class);
    }

    private function createUser(): User
    {
        $tenant = Tenant::factory()->create();

        return User::factory()->create([
            'tenant_id' => $tenant->id,
            'current_tenant_id' => $tenant->id,
        ]);
    }

    public function test_scheduled_report_store_accepts_lookup_values(): void
    {
        $user = $this->createUser();

        AutomationReportType::create([
            'tenant_id' => $user->current_tenant_id,
            'name' => 'Checklist Operacional',
            'slug' => 'operational-checklist',
            'is_active' => true,
        ]);
        AutomationReportFrequency::create([
            'tenant_id' => $user->current_tenant_id,
            'name' => 'Quinzenal',
            'slug' => 'biweekly',
            'is_active' => true,
        ]);
        AutomationReportFormat::create([
            'tenant_id' => $user->current_tenant_id,
            'name' => 'CSV',
            'slug' => 'csv',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->postJson('/api/v1/automation/reports', [
            'name' => 'Checklist Quinzenal',
            'report_type' => 'operational-checklist',
            'frequency' => 'biweekly',
            'format' => 'csv',
            'recipients' => ['ops@example.com'],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.report_type', 'operational-checklist')
            ->assertJsonPath('data.frequency', 'biweekly')
            ->assertJsonPath('data.format', 'csv');

        $this->assertDatabaseHas('scheduled_reports', [
            'tenant_id' => $user->current_tenant_id,
            'name' => 'Checklist Quinzenal',
            'report_type' => 'operational-checklist',
            'frequency' => 'biweekly',
            'format' => 'csv',
        ]);
    }

    public function test_scheduled_report_update_accepts_lookup_values(): void
    {
        $user = $this->createUser();

        AutomationReportType::create([
            'tenant_id' => $user->current_tenant_id,
            'name' => 'Relatorio Vendas',
            'slug' => 'sales-report',
            'is_active' => true,
        ]);
        AutomationReportFrequency::create([
            'tenant_id' => $user->current_tenant_id,
            'name' => 'Semanal',
            'slug' => 'weekly',
            'is_active' => true,
        ]);
        AutomationReportFormat::create([
            'tenant_id' => $user->current_tenant_id,
            'name' => 'Excel',
            'slug' => 'excel',
            'is_active' => true,
        ]);

        $report = ScheduledReport::create([
            'tenant_id' => $user->current_tenant_id,
            'name' => 'Relatorio Base',
            'report_type' => 'work-orders',
            'frequency' => 'daily',
            'format' => 'pdf',
            'recipients' => ['admin@example.com'],
            'created_by' => $user->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->putJson("/api/v1/automation/reports/{$report->id}", [
            'report_type' => 'sales-report',
            'frequency' => 'weekly',
            'format' => 'excel',
            'recipients' => ['sales@example.com'],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.report_type', 'sales-report')
            ->assertJsonPath('data.frequency', 'weekly')
            ->assertJsonPath('data.format', 'excel');

        $this->assertDatabaseHas('scheduled_reports', [
            'id' => $report->id,
            'report_type' => 'sales-report',
            'frequency' => 'weekly',
            'format' => 'excel',
        ]);
    }
}
