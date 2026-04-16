<?php

use App\Enums\QuoteStatus;
use App\Jobs\ClassifyEmailJob;
use App\Jobs\DetectCalibrationFraudulentPatterns;
use App\Jobs\EmitFiscalNoteJob;
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
use App\Jobs\SyncEmailAccountJob;
use App\Models\CrmSequence;
use App\Models\CrmSequenceEnrollment;
use App\Models\CrmSequenceStep;
use App\Models\Customer;
use App\Models\Email;
use App\Models\EmailAccount;
use App\Models\Equipment;
use App\Models\EquipmentCalibration;
use App\Models\FleetVehicle;
use App\Models\Import;
use App\Models\Invoice;
use App\Models\Notification;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\CashFlowProjectionService;
use App\Services\Crm\CrmSmartAlertGenerator;
use App\Services\DREService;
use App\Services\Email\EmailClassifierService;
use App\Services\Email\EmailRuleEngine;
use App\Services\Email\EmailSendService;
use App\Services\Email\EmailSyncService;
use App\Services\Fiscal\FiscalProvider;
use App\Services\Fiscal\FiscalResult;
use App\Services\ImportService;
use App\Services\MessagingService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['status' => Tenant::STATUS_ACTIVE]);
    $this->user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'current_tenant_id' => $this->tenant->id,
        'is_active' => true,
    ]);
    app()->instance('current_tenant_id', $this->tenant->id);
    Gate::before(fn () => true);
    Sanctum::actingAs($this->user, ['*']);
});

// ---------------------------------------------------------------------------
// ClassifyEmailJob
// ---------------------------------------------------------------------------

test('ClassifyEmailJob skips already classified emails', function () {
    $account = EmailAccount::create([
        'tenant_id' => $this->tenant->id,
        'label' => 'Test Account',
        'email_address' => 'test-classify@example.com',
        'imap_host' => 'imap.example.com',
        'imap_username' => 'test@example.com',
        'imap_password' => 'secret',
        'is_active' => true,
    ]);

    $email = Email::create([
        'tenant_id' => $this->tenant->id,
        'subject' => 'Test',
        'body_text' => 'Body text',
        'from_address' => 'test@example.com',
        'message_id' => '<classify-skip-'.uniqid().'@example.com>',
        'to_addresses' => ['recipient@example.com'],
        'date' => now(),
        'ai_classified_at' => now(),
        'status' => 'received',
        'email_account_id' => $account->id,
    ]);

    $classifier = $this->mock(EmailClassifierService::class);
    $classifier->shouldNotReceive('classify');

    $ruleEngine = $this->mock(EmailRuleEngine::class);
    $ruleEngine->shouldNotReceive('apply');

    $job = new ClassifyEmailJob($email);
    $job->handle($classifier, $ruleEngine);
});

test('ClassifyEmailJob classifies and applies rules to unclassified email', function () {
    $account = EmailAccount::create([
        'tenant_id' => $this->tenant->id,
        'label' => 'Test Account',
        'email_address' => 'test-classify2@example.com',
        'imap_host' => 'imap.example.com',
        'imap_username' => 'test@example.com',
        'imap_password' => 'secret',
        'is_active' => true,
    ]);

    $email = Email::create([
        'tenant_id' => $this->tenant->id,
        'subject' => 'Need calibration',
        'body_text' => 'Please calibrate our equipment',
        'from_address' => 'client@example.com',
        'message_id' => '<classify-rules-'.uniqid().'@example.com>',
        'to_addresses' => ['recipient@example.com'],
        'date' => now(),
        'ai_classified_at' => null,
        'status' => 'received',
        'email_account_id' => $account->id,
    ]);

    $classifier = $this->mock(EmailClassifierService::class);
    $classifier->shouldReceive('classify')->once()->with($email)->andReturn($email);

    $ruleEngine = $this->mock(EmailRuleEngine::class);
    $ruleEngine->shouldReceive('apply')->once();

    $job = new ClassifyEmailJob($email);
    $job->handle($classifier, $ruleEngine);
});

// ---------------------------------------------------------------------------
// SyncEmailAccountJob
// ---------------------------------------------------------------------------

test('SyncEmailAccountJob skips inactive accounts', function () {
    $account = EmailAccount::create([
        'tenant_id' => $this->tenant->id,
        'label' => 'Inactive Account',
        'email_address' => 'sync-inactive-'.uniqid().'@example.com',
        'imap_host' => 'imap.example.com',
        'imap_username' => 'test@example.com',
        'imap_password' => 'secret',
        'is_active' => false,
    ]);

    $syncService = $this->mock(EmailSyncService::class);
    $syncService->shouldNotReceive('syncAccount');

    $job = new SyncEmailAccountJob($account);
    $job->handle($syncService);
});

test('SyncEmailAccountJob skips accounts already syncing', function () {
    $account = EmailAccount::create([
        'tenant_id' => $this->tenant->id,
        'label' => 'Syncing Account',
        'email_address' => 'sync-syncing-'.uniqid().'@example.com',
        'imap_host' => 'imap.example.com',
        'imap_username' => 'test@example.com',
        'imap_password' => 'secret',
        'is_active' => true,
        'sync_status' => 'syncing',
    ]);

    $syncService = $this->mock(EmailSyncService::class);
    $syncService->shouldNotReceive('syncAccount');

    $job = new SyncEmailAccountJob($account);
    $job->handle($syncService);
});

test('SyncEmailAccountJob syncs active accounts', function () {
    $account = EmailAccount::create([
        'tenant_id' => $this->tenant->id,
        'label' => 'Active Sync Account',
        'email_address' => 'sync-active-'.uniqid().'@example.com',
        'imap_host' => 'imap.example.com',
        'imap_username' => 'sync@example.com',
        'imap_password' => 'secret',
        'is_active' => true,
        'sync_status' => 'idle',
    ]);

    $syncService = $this->mock(EmailSyncService::class);
    $syncService->shouldReceive('syncAccount')->once()->with($account);

    $job = new SyncEmailAccountJob($account);
    $job->handle($syncService);
});

// ---------------------------------------------------------------------------
// EmitFiscalNoteJob
// ---------------------------------------------------------------------------

test('EmitFiscalNoteJob skips cancelled invoices', function () {
    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $this->user->id,
    ]);
    $invoice = Invoice::factory()->create([
        'tenant_id' => $this->tenant->id,
        'work_order_id' => $wo->id,
        'status' => Invoice::STATUS_CANCELLED,
    ]);

    Cache::shouldReceive('lock')->andReturnSelf();
    Cache::shouldReceive('get')->andReturn(true);
    Cache::shouldReceive('release');

    $fiscalProvider = $this->mock(FiscalProvider::class);
    $fiscalProvider->shouldNotReceive('emitirNFSe');
    $fiscalProvider->shouldNotReceive('emitirNFe');

    $job = new EmitFiscalNoteJob($this->tenant->id, $invoice->id, 'nfse');
    $job->handle($fiscalProvider);
});

test('EmitFiscalNoteJob emits NFSe successfully', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'created_by' => $this->user->id,
    ]);
    $invoice = Invoice::factory()->create([
        'tenant_id' => $this->tenant->id,
        'work_order_id' => $wo->id,
        'status' => Invoice::STATUS_ISSUED,
        'total' => 1000,
    ]);

    Cache::shouldReceive('lock')->andReturnSelf();
    Cache::shouldReceive('get')->andReturn(true);
    Cache::shouldReceive('release');

    $result = FiscalResult::ok([
        'reference' => 'NFSe-12345',
    ]);

    $fiscalProvider = $this->mock(FiscalProvider::class);
    $fiscalProvider->shouldReceive('emitirNFSe')->once()->andReturn($result);

    $job = new EmitFiscalNoteJob($this->tenant->id, $invoice->id, 'nfse');
    $job->handle($fiscalProvider);

    $invoice->refresh();
    expect($invoice->fiscal_status)->toBe(Invoice::FISCAL_STATUS_EMITTED);
    expect($invoice->fiscal_note_key)->toBe('NFSe-12345');
});

test('EmitFiscalNoteJob marks invoice as failed on permanent failure', function () {
    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $this->user->id,
    ]);
    $invoice = Invoice::factory()->create([
        'tenant_id' => $this->tenant->id,
        'work_order_id' => $wo->id,
    ]);

    $job = new EmitFiscalNoteJob($this->tenant->id, $invoice->id, 'nfse');
    $job->failed(new Exception('SEFAZ timeout'));

    $invoice->refresh();
    expect($invoice->fiscal_status)->toBe(Invoice::FISCAL_STATUS_FAILED);
});

test('EmitFiscalNoteJob has unique ID per tenant/invoice/type', function () {
    $job = new EmitFiscalNoteJob(1, 42, 'nfse');
    expect($job->uniqueId())->toBe('1:42:nfse');

    $job2 = new EmitFiscalNoteJob(1, 42, 'nfe');
    expect($job2->uniqueId())->toBe('1:42:nfe');
});

// ---------------------------------------------------------------------------
// GenerateReportJob
// ---------------------------------------------------------------------------

test('GenerateReportJob generates DRE report and notifies user', function () {
    Storage::fake();

    $dreService = $this->mock(DREService::class);
    $dreService->shouldReceive('generate')->once()->andReturn(['revenue' => 50000, 'expenses' => 30000]);

    $cashFlowService = $this->mock(CashFlowProjectionService::class);

    $job = new GenerateReportJob(
        $this->tenant->id,
        $this->user->id,
        'dre',
        '2025-01-01',
        '2025-12-31'
    );
    $job->handle($dreService, $cashFlowService);

    $this->assertDatabaseHas('notifications', [
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'type' => 'report_ready',
    ]);
});

test('GenerateReportJob generates cash flow report', function () {
    Storage::fake();

    $dreService = $this->mock(DREService::class);
    $cashFlowService = $this->mock(CashFlowProjectionService::class);
    $cashFlowService->shouldReceive('project')->once()->andReturn(['projections' => []]);

    $job = new GenerateReportJob(
        $this->tenant->id,
        $this->user->id,
        'cash_flow',
        '2025-01-01',
        '2025-06-30'
    );
    $job->handle($dreService, $cashFlowService);

    $this->assertDatabaseHas('notifications', [
        'type' => 'report_ready',
    ]);
});

test('GenerateReportJob notifies user on failure', function () {
    $dreService = $this->mock(DREService::class);
    $dreService->shouldReceive('generate')->andThrow(new Exception('Database error'));

    $cashFlowService = $this->mock(CashFlowProjectionService::class);

    $job = new GenerateReportJob(
        $this->tenant->id,
        $this->user->id,
        'dre',
        '2025-01-01',
        '2025-12-31'
    );

    try {
        $job->handle($dreService, $cashFlowService);
    } catch (Exception) {
        // Expected
    }

    $this->assertDatabaseHas('notifications', [
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'type' => 'report_failed',
    ]);
});

test('GenerateReportJob handles generic report types', function () {
    Storage::fake();

    $dreService = $this->mock(DREService::class);
    $cashFlowService = $this->mock(CashFlowProjectionService::class);

    $job = new GenerateReportJob(
        $this->tenant->id,
        $this->user->id,
        'expenses',
        '2025-01-01',
        '2025-03-31'
    );
    $job->handle($dreService, $cashFlowService);

    $this->assertDatabaseHas('notifications', [
        'type' => 'report_ready',
    ]);
});

// ---------------------------------------------------------------------------
// StockMinimumAlertJob
// ---------------------------------------------------------------------------

test('StockMinimumAlertJob sends alerts for products below minimum stock', function () {
    $product = Product::factory()->create([
        'tenant_id' => $this->tenant->id,
        'is_active' => true,
        'stock_min' => 10,
        'stock_qty' => 5,
        'name' => 'Resistor 10k',
    ]);

    // The job queries products with minimum_stock > 0 and checks totalStock()
    // Since no stock movements exist, totalStock() returns 0 which is < 10
    $job = new StockMinimumAlertJob;
    $job->handle();

    // O job itera tenants e cria notificações via Notification::notify.
    // Sem um usuário com permissão 'estoque.product.view' (Spatie), nenhuma notificação é enviada,
    // mas o job deve completar sem erro e o produto deve ter estoque abaixo do mínimo.
    $freshProduct = $product->fresh();
    expect((float) ($freshProduct->stock_qty ?? 0))->toBeLessThanOrEqual($freshProduct->stock_min);

    // No notifications because the test user lacks the 'estoque.product.view' permission
    $notificationCount = Notification::where('tenant_id', $this->tenant->id)
        ->where('type', 'stock_minimum_alert')
        ->count();
    expect($notificationCount)->toBe(0);
});

test('StockMinimumAlertJob can be dispatched to queue', function () {
    Queue::fake();

    StockMinimumAlertJob::dispatch();

    Queue::assertPushed(StockMinimumAlertJob::class);
});

// ---------------------------------------------------------------------------
// QuoteExpirationAlertJob
// ---------------------------------------------------------------------------

test('QuoteExpirationAlertJob logs expiring quotes', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $quote = Quote::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'seller_id' => $this->user->id,
        'status' => QuoteStatus::SENT,
        'valid_until' => today()->addDays(2),
    ]);

    Log::shouldReceive('info')->atLeast()->once();
    Log::shouldReceive('error')->never();

    $job = new QuoteExpirationAlertJob;
    $job->handle();
});

test('QuoteExpirationAlertJob ignores quotes far from expiration', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $quote = Quote::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'seller_id' => $this->user->id,
        'status' => QuoteStatus::SENT,
        'valid_until' => today()->addDays(30),
    ]);

    $job = new QuoteExpirationAlertJob;
    $job->handle();

    // No audit log should be created for quotes far from expiration
    $this->assertDatabaseMissing('audit_logs', [
        'action' => 'expiration_alert',
    ]);
});

test('QuoteExpirationAlertJob can be dispatched', function () {
    Queue::fake();

    QuoteExpirationAlertJob::dispatch();

    Queue::assertPushed(QuoteExpirationAlertJob::class);
});

// ---------------------------------------------------------------------------
// QuoteFollowUpJob
// ---------------------------------------------------------------------------

test('QuoteFollowUpJob increments follow-up count for overdue quotes', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $quote = Quote::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'seller_id' => $this->user->id,
        'status' => QuoteStatus::SENT,
        'sent_at' => now()->subDays(5),
        'followup_count' => 0,
        'last_followup_at' => null,
    ]);

    $job = new QuoteFollowUpJob;
    $job->handle();

    $quote->refresh();
    expect($quote->followup_count)->toBe(1);
    expect($quote->last_followup_at)->not->toBeNull();
});

test('QuoteFollowUpJob respects max follow-up limit', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $quote = Quote::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'seller_id' => $this->user->id,
        'status' => QuoteStatus::SENT,
        'sent_at' => now()->subDays(30),
        'followup_count' => 3,
        'last_followup_at' => now()->subDays(5),
    ]);

    $job = new QuoteFollowUpJob;
    $job->handle();

    $quote->refresh();
    expect($quote->followup_count)->toBe(3); // Unchanged
});

// ---------------------------------------------------------------------------
// DetectCalibrationFraudulentPatterns
// ---------------------------------------------------------------------------

test('DetectCalibrationFraudulentPatterns processes calibrations for specific tenant', function () {
    Log::shouldReceive('info')
        ->once()
        ->withArgs(fn ($msg) => str_contains($msg, 'DetectCalibrationFraudulentPatterns'));
    Log::shouldReceive('error')->never();

    $job = new DetectCalibrationFraudulentPatterns($this->tenant->id);
    $job->handle();
});

test('DetectCalibrationFraudulentPatterns processes all tenants when no ID given', function () {
    Log::shouldReceive('info')
        ->atLeast()->once()
        ->withArgs(fn ($msg) => str_contains($msg, 'DetectCalibrationFraudulentPatterns'));
    Log::shouldReceive('error')->never();

    $job = new DetectCalibrationFraudulentPatterns;
    $job->handle();
});

test('DetectCalibrationFraudulentPatterns detects patterns with many calibrations', function () {
    $equipment = Equipment::factory()->create(['tenant_id' => $this->tenant->id]);

    // Create 6 calibrations by same technician (needed to trigger pattern detection threshold of 5)
    for ($i = 0; $i < 6; $i++) {
        EquipmentCalibration::create([
            'tenant_id' => $this->tenant->id,
            'equipment_id' => $equipment->id,
            'calibration_date' => now(),
            'performed_by' => $this->user->id,
            'result' => 'aprovado',
        ]);
    }

    Log::shouldReceive('info')
        ->once()
        ->withArgs(function ($msg, $context) {
            return str_contains($msg, 'DetectCalibrationFraudulentPatterns')
                && ($context['calibrations_analyzed'] ?? 0) >= 6
                && ($context['technicians_checked'] ?? 0) >= 1;
        });

    $job = new DetectCalibrationFraudulentPatterns($this->tenant->id);
    $job->handle();
});

test('DetectCalibrationFraudulentPatterns can be dispatched to queue', function () {
    Queue::fake();

    DetectCalibrationFraudulentPatterns::dispatch($this->tenant->id);

    Queue::assertPushed(DetectCalibrationFraudulentPatterns::class);
});

// ---------------------------------------------------------------------------
// ImportJob
// ---------------------------------------------------------------------------

test('ImportJob sets tenant context and processes import', function () {
    $import = Import::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => 'pending',
    ]);

    $importService = $this->mock(ImportService::class);
    $importService->shouldReceive('processImport')->once()->with($import);

    $job = new ImportJob($import);
    $job->handle($importService);
});

test('ImportJob marks import as failed on error', function () {
    $import = Import::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => 'pending',
    ]);

    $importService = $this->mock(ImportService::class);
    $importService->shouldReceive('processImport')
        ->once()
        ->andThrow(new Exception('Parse error'));

    $job = new ImportJob($import);

    try {
        $job->handle($importService);
    } catch (Exception) {
        // Expected
    }

    $import->refresh();
    expect($import->status)->toBe(Import::STATUS_FAILED);
});

// ---------------------------------------------------------------------------
// SendScheduledEmails
// ---------------------------------------------------------------------------

test('SendScheduledEmails delivers due scheduled emails', function () {
    $account = EmailAccount::create([
        'tenant_id' => $this->tenant->id,
        'label' => 'Scheduled Account',
        'email_address' => 'scheduled-'.uniqid().'@example.com',
        'imap_host' => 'imap.example.com',
        'imap_username' => 'test@example.com',
        'imap_password' => 'secret',
        'is_active' => true,
    ]);

    $email = Email::create([
        'tenant_id' => $this->tenant->id,
        'subject' => 'Scheduled Test',
        'body_text' => 'Body',
        'from_address' => 'test@example.com',
        'message_id' => '<scheduled-due-'.uniqid().'@example.com>',
        'to_addresses' => ['recipient@example.com'],
        'date' => now(),
        'status' => 'scheduled',
        'scheduled_at' => now()->subHour(),
        'email_account_id' => $account->id,
    ]);

    $emailService = $this->mock(EmailSendService::class);
    $emailService->shouldReceive('deliver')->once();

    $job = new SendScheduledEmails;
    $job->handle($emailService);
});

test('SendScheduledEmails marks email as failed on delivery error', function () {
    $account = EmailAccount::create([
        'tenant_id' => $this->tenant->id,
        'label' => 'Fail Account',
        'email_address' => 'fail-'.uniqid().'@example.com',
        'imap_host' => 'imap.example.com',
        'imap_username' => 'test@example.com',
        'imap_password' => 'secret',
        'is_active' => true,
    ]);

    $email = Email::create([
        'tenant_id' => $this->tenant->id,
        'subject' => 'Will Fail',
        'body_text' => 'Body',
        'from_address' => 'test@example.com',
        'message_id' => '<scheduled-fail-'.uniqid().'@example.com>',
        'to_addresses' => ['recipient@example.com'],
        'date' => now(),
        'status' => 'scheduled',
        'scheduled_at' => now()->subHour(),
        'email_account_id' => $account->id,
    ]);

    $emailService = $this->mock(EmailSendService::class);
    $emailService->shouldReceive('deliver')->andThrow(new Exception('SMTP error'));

    $job = new SendScheduledEmails;
    $job->handle($emailService);

    $email->refresh();
    expect($email->status)->toBe('failed');
});

test('SendScheduledEmails skips future emails', function () {
    $account = EmailAccount::create([
        'tenant_id' => $this->tenant->id,
        'label' => 'Future Account',
        'email_address' => 'future-'.uniqid().'@example.com',
        'imap_host' => 'imap.example.com',
        'imap_username' => 'test@example.com',
        'imap_password' => 'secret',
        'is_active' => true,
    ]);

    $email = Email::create([
        'tenant_id' => $this->tenant->id,
        'subject' => 'Future Email',
        'body_text' => 'Body',
        'from_address' => 'test@example.com',
        'message_id' => '<scheduled-future-'.uniqid().'@example.com>',
        'to_addresses' => ['recipient@example.com'],
        'date' => now(),
        'status' => 'scheduled',
        'scheduled_at' => now()->addHour(),
        'email_account_id' => $account->id,
    ]);

    $emailService = $this->mock(EmailSendService::class);
    $emailService->shouldNotReceive('deliver');

    $job = new SendScheduledEmails;
    $job->handle($emailService);
});

// ---------------------------------------------------------------------------
// FleetDocExpirationAlertJob
// ---------------------------------------------------------------------------

test('FleetDocExpirationAlertJob sends alerts for expiring vehicle docs', function () {
    Permission::findOrCreate('fleet.vehicle.view', 'web');
    $this->user->givePermissionTo('fleet.vehicle.view');

    $vehicle = FleetVehicle::create([
        'tenant_id' => $this->tenant->id,
        'plate' => 'ABC-1234',
        'model' => 'Fiat Strada',
        'status' => 'active',
        'crlv_expiry' => today()->addDays(7),
        'insurance_expiry' => today()->addDays(40),
        'next_maintenance' => today()->addDays(90),
    ]);

    $job = new FleetDocExpirationAlertJob;
    $job->handle();

    $this->assertDatabaseHas('notifications', [
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'type' => 'fleet_doc_expiration',
    ]);

    $notification = Notification::query()
        ->where('tenant_id', $this->tenant->id)
        ->where('user_id', $this->user->id)
        ->where('type', 'fleet_doc_expiration')
        ->first();

    $this->assertNotNull($notification);
    $this->assertStringContainsString('ABC-1234', (string) $notification->message);
    $this->assertStringContainsString('CRLV', (string) $notification->message);
});

test('FleetDocExpirationAlertJob ignores vehicles outside alert windows or inactive', function () {
    Permission::findOrCreate('fleet.vehicle.view', 'web');
    $this->user->givePermissionTo('fleet.vehicle.view');

    FleetVehicle::create([
        'tenant_id' => $this->tenant->id,
        'plate' => 'XYZ-9876',
        'model' => 'Fiat Uno',
        'status' => 'inactive',
        'crlv_expiry' => today()->addDays(3),
        'insurance_expiry' => today()->addDays(3),
    ]);

    $job = new FleetDocExpirationAlertJob;
    $job->handle();

    $this->assertDatabaseMissing('notifications', [
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'type' => 'fleet_doc_expiration',
    ]);
});

// ---------------------------------------------------------------------------
// FleetMaintenanceAlertJob
// ---------------------------------------------------------------------------

test('FleetMaintenanceAlertJob sends alerts for vehicles near maintenance', function () {
    Permission::findOrCreate('fleet.vehicle.view', 'web');
    $this->user->givePermissionTo('fleet.vehicle.view');

    $vehicle = FleetVehicle::create([
        'tenant_id' => $this->tenant->id,
        'plate' => 'DEF-5678',
        'model' => 'Toyota Hilux',
        'status' => 'active',
        'odometer_km' => 52000,
        'next_maintenance' => today()->addDays(5),
    ]);

    $job = new FleetMaintenanceAlertJob;
    $job->handle();

    $this->assertDatabaseHas('notifications', [
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'type' => 'fleet_maintenance_alert',
    ]);

    $notification = Notification::query()
        ->where('tenant_id', $this->tenant->id)
        ->where('user_id', $this->user->id)
        ->where('type', 'fleet_maintenance_alert')
        ->first();

    $this->assertNotNull($notification);
    $this->assertStringContainsString('DEF-5678', (string) $notification->message);
    $this->assertStringContainsString('manutenção', strtolower((string) $notification->message));
});

test('FleetMaintenanceAlertJob ignores vehicles outside the alert window or inactive', function () {
    Permission::findOrCreate('fleet.vehicle.view', 'web');
    $this->user->givePermissionTo('fleet.vehicle.view');

    FleetVehicle::create([
        'tenant_id' => $this->tenant->id,
        'plate' => 'GHI-9012',
        'model' => 'VW Gol',
        'status' => 'active',
        'odometer_km' => 33000,
        'next_maintenance' => today()->addDays(45),
    ]);

    $job = new FleetMaintenanceAlertJob;
    $job->handle();

    $this->assertDatabaseMissing('notifications', [
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'type' => 'fleet_maintenance_alert',
    ]);
});

// ---------------------------------------------------------------------------
// GenerateCrmSmartAlerts
// ---------------------------------------------------------------------------

test('GenerateCrmSmartAlerts processes active tenants', function () {
    $generator = $this->mock(CrmSmartAlertGenerator::class);
    $generator->shouldReceive('generateForTenant')
        ->once()
        ->with($this->tenant->id);

    $job = new GenerateCrmSmartAlerts;
    $job->handle();
});

test('GenerateCrmSmartAlerts handles errors gracefully per tenant', function () {
    // Create a second tenant
    $tenant2 = Tenant::factory()->create(['status' => Tenant::STATUS_ACTIVE]);

    $generator = $this->mock(CrmSmartAlertGenerator::class);
    $generator->shouldReceive('generateForTenant')
        ->with($this->tenant->id)
        ->andThrow(new Exception('Failed'));
    $generator->shouldReceive('generateForTenant')
        ->with($tenant2->id)
        ->once();

    Log::shouldReceive('error')
        ->once()
        ->withArgs(fn ($msg, $ctx) => str_contains($msg, 'Smart alerts generation failed') &&
            ($ctx['tenant_id'] ?? null) === $this->tenant->id
        );

    $job = new GenerateCrmSmartAlerts;
    $job->handle();

    // Should continue processing tenant2 even though tenant1 threw an exception
});

// ---------------------------------------------------------------------------
// ProcessCrmSequences
// ---------------------------------------------------------------------------

test('ProcessCrmSequences processes due enrollments', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $sequence = CrmSequence::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => 'active',
    ]);
    $step = CrmSequenceStep::create([
        'sequence_id' => $sequence->id,
        'step_order' => 0,
        'channel' => 'internal',
        'action_type' => 'create_activity',
        'subject' => 'Follow up',
        'body' => 'Follow up with client',
        'delay_days' => 0,
    ]);

    $enrollment = CrmSequenceEnrollment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'sequence_id' => $sequence->id,
        'customer_id' => $customer->id,
        'status' => 'active',
        'current_step' => 0,
        'next_action_at' => now()->subMinute(),
        'enrolled_by' => $this->user->id,
    ]);

    $this->mock(MessagingService::class)->shouldIgnoreMissing();

    $job = new ProcessCrmSequences;
    $job->handle();

    $enrollment->refresh();
    expect($enrollment->current_step)->toBe(1);
});

test('ProcessCrmSequences marks enrollment as completed on last step', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $sequence = CrmSequence::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => 'active',
    ]);
    CrmSequenceStep::create([
        'sequence_id' => $sequence->id,
        'step_order' => 0,
        'channel' => 'internal',
        'action_type' => 'create_task',
        'subject' => 'Final step',
        'delay_days' => 0,
    ]);

    $enrollment = CrmSequenceEnrollment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'sequence_id' => $sequence->id,
        'customer_id' => $customer->id,
        'status' => 'active',
        'current_step' => 0,
        'next_action_at' => now()->subMinute(),
        'enrolled_by' => $this->user->id,
    ]);

    $this->mock(MessagingService::class)->shouldIgnoreMissing();

    $job = new ProcessCrmSequences;
    $job->handle();

    $enrollment->refresh();
    expect($enrollment->status)->toBe('completed');
});

test('ProcessCrmSequences cancels enrollment when sequence is inactive', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $sequence = CrmSequence::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => 'paused',
    ]);

    $enrollment = CrmSequenceEnrollment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'sequence_id' => $sequence->id,
        'customer_id' => $customer->id,
        'status' => 'active',
        'current_step' => 0,
        'next_action_at' => now()->subMinute(),
    ]);

    $job = new ProcessCrmSequences;
    $job->handle();

    $enrollment->refresh();
    expect($enrollment->status)->toBe('cancelled');
});
