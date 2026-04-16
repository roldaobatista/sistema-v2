<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Analytics;

use App\Jobs\RunDataExportJob;
use App\Models\AnalyticsDataset;
use App\Models\DataExportJob;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DataExportJobControllerTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        $this->setTenantContext($this->tenant->id);

        foreach ([
            'analytics.export.create',
            'analytics.export.view',
            'analytics.export.download',
        ] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
    }

    public function test_store_creates_pending_export_job_and_dispatches_queue_job(): void
    {
        Queue::fake();
        Sanctum::actingAs($this->user, ['*']);
        $this->user->givePermissionTo('analytics.export.create');

        $dataset = AnalyticsDataset::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
        ]);

        $this->postJson('/api/v1/analytics/export-jobs', [
            'name' => 'Exportacao OS',
            'analytics_dataset_id' => $dataset->id,
            'output_format' => 'json',
            'filters' => ['status' => 'open'],
        ])->assertCreated()
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('data_export_jobs', [
            'tenant_id' => $this->tenant->id,
            'analytics_dataset_id' => $dataset->id,
            'status' => 'pending',
        ]);

        Queue::assertPushed(RunDataExportJob::class);
    }

    public function test_store_requires_create_permission(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $dataset = AnalyticsDataset::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
        ]);

        $this->postJson('/api/v1/analytics/export-jobs', [
            'name' => 'Sem permissao',
            'analytics_dataset_id' => $dataset->id,
            'output_format' => 'json',
        ])->assertForbidden();
    }

    public function test_retry_resets_failed_job_to_pending(): void
    {
        Queue::fake();
        Sanctum::actingAs($this->user, ['*']);
        $this->user->givePermissionTo('analytics.export.create');

        $job = DataExportJob::factory()->failed()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
        ]);

        $this->postJson("/api/v1/analytics/export-jobs/{$job->id}/retry")
            ->assertOk()
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('data_export_jobs', [
            'id' => $job->id,
            'status' => 'pending',
            'error_message' => null,
        ]);
    }

    public function test_cancel_marks_job_as_cancelled(): void
    {
        Sanctum::actingAs($this->user, ['*']);
        $this->user->givePermissionTo('analytics.export.create');

        $job = DataExportJob::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'status' => 'pending',
        ]);

        $this->postJson("/api/v1/analytics/export-jobs/{$job->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
    }

    public function test_download_streams_completed_file(): void
    {
        Storage::fake('local');
        Sanctum::actingAs($this->user, ['*']);
        $this->user->givePermissionTo('analytics.export.download');

        Storage::disk('local')->put('analytics/test-export.json', '{"rows":1}');

        $job = DataExportJob::factory()->completed()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'output_format' => 'json',
            'output_path' => 'analytics/test-export.json',
        ]);

        $this->get("/api/v1/analytics/export-jobs/{$job->id}/download")
            ->assertOk();
    }
}
