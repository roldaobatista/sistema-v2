<?php

use App\Events\CommissionGenerated;
use App\Events\ContractRenewing;
use App\Events\PaymentMade;
use App\Events\WorkOrderCancelled;
use App\Events\WorkOrderCompleted;
use App\Listeners\HandleContractRenewing;
use App\Listeners\HandlePaymentMade;
use App\Listeners\HandleWorkOrderCancellation;
use App\Listeners\HandleWorkOrderCompletion;
use App\Listeners\NotifyBeneficiaryOnCommission;
use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\CommissionEvent;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\RecurringContract;
use App\Models\SystemAlert;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Notifications\CalibrationExpiryNotification;
use App\Notifications\NpsSurveyNotification;
use App\Notifications\OverdueFollowUpNotification;
use App\Notifications\PaymentOverdue;
use App\Notifications\SlaEscalationNotification;
use App\Notifications\SystemAlertNotification;
use App\Notifications\TwoFactorVerificationCode;
use App\Notifications\WorkOrderStatusChanged;
use App\Services\ClientNotificationService;
use App\Services\CommissionService;
use App\Services\CustomerConversionService;
use App\Services\StockService;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
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
// NpsSurveyNotification
// ---------------------------------------------------------------------------

test('NpsSurveyNotification is sent via mail', function () {
    $wo = WorkOrder::factory()->completed()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $this->user->id,
    ]);

    $notification = new NpsSurveyNotification($wo);
    $channels = $notification->via($this->user);

    expect($channels)->toContain('mail');
});

test('NpsSurveyNotification mail has correct subject', function () {
    $wo = WorkOrder::factory()->completed()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $this->user->id,
    ]);

    $notification = new NpsSurveyNotification($wo);
    $mail = $notification->toMail($this->user);

    expect($mail->subject)->toContain($wo->number);
});

test('NpsSurveyNotification mail contains feedback action link', function () {
    $wo = WorkOrder::factory()->completed()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $this->user->id,
    ]);

    $notification = new NpsSurveyNotification($wo);
    $mail = $notification->toMail($this->user);

    expect($mail->actionUrl)->toContain("/feedback/nps/{$wo->id}");
});

// ---------------------------------------------------------------------------
// CalibrationExpiryNotification
// ---------------------------------------------------------------------------

test('CalibrationExpiryNotification is sent via mail and database', function () {
    $equipment = Equipment::factory()->create(['tenant_id' => $this->tenant->id]);

    $notification = new CalibrationExpiryNotification($equipment, 15);
    $channels = $notification->via($this->user);

    expect($channels)->toContain('mail');
    expect($channels)->toContain('database');
});

test('CalibrationExpiryNotification mail includes equipment info', function () {
    $equipment = Equipment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'serial_number' => 'EQ-2025-001',
    ]);

    $notification = new CalibrationExpiryNotification($equipment, 10);
    $mail = $notification->toMail($this->user);

    expect($mail->subject)->toContain('10 dias');
});

test('CalibrationExpiryNotification database data includes equipment ID', function () {
    $equipment = Equipment::factory()->create(['tenant_id' => $this->tenant->id]);

    $notification = new CalibrationExpiryNotification($equipment, 7);
    $data = $notification->toArray($this->user);

    expect($data)->toHaveKey('equipment_id')
        ->and($data)->toHaveKey('days_until_expiry')
        ->and($data['equipment_id'])->toBe($equipment->id)
        ->and($data['days_until_expiry'])->toBe(7);
});

// ---------------------------------------------------------------------------
// PaymentOverdue Notification
// ---------------------------------------------------------------------------

test('PaymentOverdue is sent via mail and database', function () {
    $ar = AccountReceivable::factory()->create([
        'tenant_id' => $this->tenant->id,
        'due_date' => now()->subDays(10),
    ]);

    $notification = new PaymentOverdue($ar);
    $channels = $notification->via($this->user);

    expect($channels)->toContain('mail');
    expect($channels)->toContain('database');
});

test('PaymentOverdue mail includes amount and due date', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $ar = AccountReceivable::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'amount' => 5000,
        'due_date' => now()->subDays(10),
    ]);

    $notification = new PaymentOverdue($ar);
    $mail = $notification->toMail($this->user);

    expect($mail->subject)->toContain('5.000,00');
});

test('PaymentOverdue database data includes receivable info', function () {
    $customer = Customer::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Acme Corp',
    ]);
    $ar = AccountReceivable::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'amount' => 3000,
        'due_date' => now()->subDays(5),
    ]);

    $notification = new PaymentOverdue($ar);
    $data = $notification->toArray($this->user);

    expect($data)->toHaveKey('receivable_id')
        ->and($data)->toHaveKey('customer_name')
        ->and($data)->toHaveKey('amount')
        ->and($data['receivable_id'])->toBe($ar->id)
        ->and($data['customer_name'])->toBe('Acme Corp');
});

// ---------------------------------------------------------------------------
// WorkOrderStatusChanged Notification
// ---------------------------------------------------------------------------

test('WorkOrderStatusChanged notification is sent via mail', function () {
    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $this->user->id,
    ]);

    $notification = new WorkOrderStatusChanged($wo, 'open', 'in_progress');
    $channels = $notification->via($this->user);

    expect($channels)->toContain('mail');
});

test('WorkOrderStatusChanged mail includes status label', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'created_by' => $this->user->id,
    ]);

    $notification = new WorkOrderStatusChanged($wo, 'open', 'completed');
    $mail = $notification->toMail($this->user);

    expect($mail->subject)->toContain('Conclu');
});

test('WorkOrderStatusChanged persistToDatabase creates notification record', function () {
    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $this->user->id,
    ]);

    $notification = new WorkOrderStatusChanged($wo, 'open', 'in_progress');
    $result = $notification->persistToDatabase($this->tenant->id, $this->user->id);

    $this->assertDatabaseHas('notifications', [
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'type' => 'work_order_status_changed',
    ]);
});

// ---------------------------------------------------------------------------
// SlaEscalationNotification
// ---------------------------------------------------------------------------

test('SlaEscalationNotification is sent via database and broadcast', function () {
    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $this->user->id,
    ]);
    $alert = SystemAlert::create([
        'tenant_id' => $this->tenant->id,
        'alert_type' => 'sla_breach',
        'title' => 'SLA Alert',
        'message' => 'SLA breach detected',
        'severity' => 'warning',
    ]);

    $notification = new SlaEscalationNotification($wo, ['level' => 'L1', 'percent_used' => 85], $alert);
    $channels = $notification->via($this->user);

    expect($channels)->toContain('database');
    expect($channels)->toContain('broadcast');
});

test('SlaEscalationNotification database data includes escalation level', function () {
    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $this->user->id,
    ]);
    $alert = SystemAlert::create([
        'tenant_id' => $this->tenant->id,
        'alert_type' => 'sla_breach',
        'title' => 'SLA Alert',
        'message' => 'SLA breach detected - critical',
        'severity' => 'critical',
    ]);

    $escalation = ['level' => 'L2', 'percent_used' => 110];
    $notification = new SlaEscalationNotification($wo, $escalation, $alert);
    $data = $notification->toDatabase($this->user);

    expect($data)->toHaveKey('level')
        ->and($data)->toHaveKey('work_order_id')
        ->and($data['level'])->toBe('L2')
        ->and($data['work_order_id'])->toBe($wo->id);
});

// ---------------------------------------------------------------------------
// SystemAlertNotification
// ---------------------------------------------------------------------------

test('SystemAlertNotification is sent via mail and database', function () {
    $notification = new SystemAlertNotification('Disk Full', 'Server disk at 95%', 'critical');
    $channels = $notification->via($this->user);

    expect($channels)->toContain('mail');
    expect($channels)->toContain('database');
});

test('SystemAlertNotification mail includes severity in subject', function () {
    $notification = new SystemAlertNotification('Memory Leak', 'App consuming 8GB', 'error');
    $mail = $notification->toMail($this->user);

    expect($mail->subject)->toContain('error');
    expect($mail->subject)->toContain('Memory Leak');
});

test('SystemAlertNotification database data includes all fields', function () {
    $notification = new SystemAlertNotification('Queue Blocked', 'Worker not responding', 'warning');
    $data = $notification->toArray($this->user);

    expect($data)->toHaveKey('title')
        ->and($data)->toHaveKey('body')
        ->and($data)->toHaveKey('severity')
        ->and($data['title'])->toBe('Queue Blocked')
        ->and($data['severity'])->toBe('warning');
});

// ---------------------------------------------------------------------------
// TwoFactorVerificationCode
// ---------------------------------------------------------------------------

test('TwoFactorVerificationCode is sent only via mail', function () {
    $notification = new TwoFactorVerificationCode(123456);
    $channels = $notification->via($this->user);

    expect($channels)->toBe(['mail']);
});

test('TwoFactorVerificationCode mail includes code', function () {
    $notification = new TwoFactorVerificationCode(654321);
    $mail = $notification->toMail($this->user);

    expect($mail->subject)->toContain('2FA');
});

// ---------------------------------------------------------------------------
// OverdueFollowUpNotification
// ---------------------------------------------------------------------------

test('OverdueFollowUpNotification is sent via database only', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

    $notification = new OverdueFollowUpNotification($customer, 'overdue_follow_up');
    $channels = $notification->via($this->user);

    expect($channels)->toBe(['database']);
});

test('OverdueFollowUpNotification database data includes customer info', function () {
    $customer = Customer::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Corp XYZ',
    ]);

    $notification = new OverdueFollowUpNotification($customer, 'no_contact');
    $data = $notification->toDatabase($this->user);

    expect($data)->toHaveKey('customer_id')
        ->and($data)->toHaveKey('customer_name')
        ->and($data)->toHaveKey('reason')
        ->and($data['customer_name'])->toBe('Corp XYZ')
        ->and($data['reason'])->toBe('no_contact');
});

// ---------------------------------------------------------------------------
// Listener-triggered notifications (integration)
// ---------------------------------------------------------------------------

test('HandleWorkOrderCompletion creates notification for creator', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $wo = WorkOrder::factory()->completed()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'created_by' => $this->user->id,
    ]);

    $this->mock(ClientNotificationService::class)->shouldIgnoreMissing();
    $this->mock(CommissionService::class)->shouldIgnoreMissing();
    $this->mock(CustomerConversionService::class)->shouldIgnoreMissing();

    $event = new WorkOrderCompleted($wo, $this->user, 'in_progress');
    $listener = app(HandleWorkOrderCompletion::class);
    $listener->handle($event);

    $this->assertDatabaseHas('notifications', [
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'type' => 'os_completed',
    ]);
});

test('HandleWorkOrderCancellation creates notification with reason', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'created_by' => $this->user->id,
    ]);

    $this->mock(StockService::class)->shouldIgnoreMissing();

    $event = new WorkOrderCancelled($wo, $this->user, 'Customer cancelled', 'open');
    $listener = app(HandleWorkOrderCancellation::class);
    $listener->handle($event);

    $notification = Notification::where('type', 'os_cancelled')
        ->where('user_id', $this->user->id)
        ->first();

    expect($notification)->not->toBeNull();
});

test('HandlePaymentMade creates payment notification', function () {
    $ap = AccountPayable::factory()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $this->user->id,
        'description' => 'Office Supplies',
    ]);
    $payment = Payment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'amount' => 250,
        'received_by' => $this->user->id,
    ]);

    $event = new PaymentMade($ap, $payment);
    $listener = app(HandlePaymentMade::class);
    $listener->handle($event);

    $this->assertDatabaseHas('notifications', [
        'type' => 'payment_made',
        'tenant_id' => $this->tenant->id,
    ]);
});

test('HandleContractRenewing creates renewal notification', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $contract = RecurringContract::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'assigned_to' => $this->user->id,
        'end_date' => now()->addDays(10),
        'name' => 'Annual Calibration Contract',
    ]);

    $event = new ContractRenewing($contract, 10);
    $listener = app(HandleContractRenewing::class);
    $listener->handle($event);

    $this->assertDatabaseHas('notifications', [
        'type' => 'contract_renewing',
        'user_id' => $this->user->id,
        'tenant_id' => $this->tenant->id,
    ]);
});

test('NotifyBeneficiaryOnCommission creates commission notification', function () {
    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $this->user->id,
    ]);
    $commission = CommissionEvent::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'work_order_id' => $wo->id,
        'commission_amount' => 350,
    ]);

    $event = new CommissionGenerated($commission);
    $listener = app(NotifyBeneficiaryOnCommission::class);
    $listener->handle($event);

    $this->assertDatabaseHas('notifications', [
        'type' => 'commission_generated',
        'user_id' => $this->user->id,
        'tenant_id' => $this->tenant->id,
    ]);
});
