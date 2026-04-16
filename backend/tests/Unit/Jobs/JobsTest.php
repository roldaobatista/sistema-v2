<?php

namespace Tests\Unit\Jobs;

use App\Jobs\DetectCalibrationFraudulentPatterns;
use App\Jobs\FleetDocExpirationAlertJob;
use App\Jobs\FleetMaintenanceAlertJob;
use App\Jobs\GenerateCrmSmartAlerts;
use App\Jobs\GenerateReportJob;
use App\Jobs\ImportJob;
use App\Jobs\ProcessCrmSequences;
use App\Jobs\QuoteExpirationAlertJob;
use App\Jobs\QuoteFollowUpJob;
use App\Jobs\SendScheduledEmails;
use App\Jobs\StockMinimumAlertJob;
use App\Models\Import;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class JobsTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);

        app()->instance('current_tenant_id', $this->tenant->id);
        $this->actingAs($this->user);
    }

    public function test_import_job_can_be_instantiated(): void
    {
        $import = Import::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);

        $job = new ImportJob($import);
        $this->assertInstanceOf(ImportJob::class, $job);
    }

    public function test_generate_report_job_can_be_instantiated(): void
    {
        $job = new GenerateReportJob(
            $this->tenant->id,
            $this->user->id,
            'customers',
            now()->subMonth()->toDateString(),
            now()->toDateString()
        );
        $this->assertInstanceOf(GenerateReportJob::class, $job);
    }

    public function test_detect_calibration_fraudulent_patterns_job_can_be_instantiated(): void
    {
        $job = new DetectCalibrationFraudulentPatterns($this->tenant->id);
        $this->assertInstanceOf(DetectCalibrationFraudulentPatterns::class, $job);
    }

    public function test_fleet_doc_expiration_alert_job_can_be_instantiated(): void
    {
        $job = new FleetDocExpirationAlertJob;
        $this->assertInstanceOf(FleetDocExpirationAlertJob::class, $job);
    }

    public function test_fleet_maintenance_alert_job_can_be_instantiated(): void
    {
        $job = new FleetMaintenanceAlertJob;
        $this->assertInstanceOf(FleetMaintenanceAlertJob::class, $job);
    }

    public function test_stock_minimum_alert_job_can_be_instantiated(): void
    {
        $job = new StockMinimumAlertJob;
        $this->assertInstanceOf(StockMinimumAlertJob::class, $job);
    }

    public function test_quote_expiration_alert_job_can_be_instantiated(): void
    {
        $job = new QuoteExpirationAlertJob;
        $this->assertInstanceOf(QuoteExpirationAlertJob::class, $job);
    }

    public function test_quote_follow_up_job_can_be_instantiated(): void
    {
        $job = new QuoteFollowUpJob;
        $this->assertInstanceOf(QuoteFollowUpJob::class, $job);
    }

    public function test_generate_crm_smart_alerts_job_can_be_instantiated(): void
    {
        $job = new GenerateCrmSmartAlerts;
        $this->assertInstanceOf(GenerateCrmSmartAlerts::class, $job);
    }

    public function test_process_crm_sequences_job_can_be_instantiated(): void
    {
        $job = new ProcessCrmSequences;
        $this->assertInstanceOf(ProcessCrmSequences::class, $job);
    }

    public function test_send_scheduled_emails_job_can_be_instantiated(): void
    {
        $job = new SendScheduledEmails;
        $this->assertInstanceOf(SendScheduledEmails::class, $job);
    }
}
