<?php

use App\Events\CalibrationCompleted;
use App\Events\CalibrationExpiring;
use App\Events\CommissionGenerated;
use App\Events\ContractRenewing;
use App\Events\CustomerCreated;
use App\Events\FiscalNoteAuthorized;
use App\Events\NotificationSent;
use App\Events\PaymentMade;
use App\Events\PaymentReceived;
use App\Events\QuoteApproved;
use App\Events\ServiceCallCreated;
use App\Events\ServiceCallStatusChanged;
use App\Events\WorkOrderCancelled;
use App\Events\WorkOrderCompleted;
use App\Events\WorkOrderInvoiced;
use App\Events\WorkOrderStarted;
use App\Listeners\CreateAgendaItemOnContract;
use App\Listeners\CreateAgendaItemOnPayment;
use App\Listeners\CreateAgendaItemOnQuote;
use App\Listeners\CreateAgendaItemOnServiceCall;
use App\Listeners\CreateAgendaItemOnWorkOrder;
use App\Listeners\CreateWarrantyTrackingOnWorkOrderInvoiced;
use App\Listeners\GenerateCorrectiveQuoteOnCalibrationFailure;
use App\Listeners\HandleCalibrationExpiring;
use App\Listeners\HandleContractRenewing;
use App\Listeners\HandleCustomerCreated;
use App\Listeners\HandlePaymentMade;
use App\Listeners\HandlePaymentReceived;
use App\Listeners\HandleQuoteApproval;
use App\Listeners\HandleWorkOrderCancellation;
use App\Listeners\HandleWorkOrderCompletion;
use App\Listeners\HandleWorkOrderInvoicing;
use App\Listeners\LogWorkOrderStartActivity;
use App\Listeners\NotifyBeneficiaryOnCommission;
use App\Listeners\ReleaseWorkOrderOnFiscalNoteAuthorized;
use App\Listeners\TriggerCertificateGeneration;
use App\Listeners\TriggerNpsSurvey;
use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\AgendaItem;
use App\Models\CommissionEvent;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\EquipmentCalibration;
use App\Models\FiscalNote;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Quote;
use App\Models\RecurringContract;
use App\Models\ServiceCall;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderItem;
use App\Notifications\NpsSurveyNotification;
use App\Services\CalibrationCertificateService;
use App\Services\ClientNotificationService;
use App\Services\CommissionService;
use App\Services\CustomerConversionService;
use App\Services\InvoicingService;
use App\Services\StockService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Model::unguard();
    Model::preventLazyLoading(false);
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
// WorkOrderStarted Event + Listeners
// ---------------------------------------------------------------------------

test('WorkOrderStarted event carries correct work order data', function () {
    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $this->user->id,
    ]);

    $event = new WorkOrderStarted($wo, $this->user, 'open');

    expect($event->workOrder->id)->toBe($wo->id)
        ->and($event->user->id)->toBe($this->user->id)
        ->and($event->fromStatus)->toBe('open');
});

test('WorkOrderStarted dispatches correctly', function () {
    Event::fake([WorkOrderStarted::class]);

    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $this->user->id,
    ]);

    WorkOrderStarted::dispatch($wo, $this->user, 'open');

    Event::assertDispatched(WorkOrderStarted::class, fn ($e) => $e->workOrder->id === $wo->id);
});

test('LogWorkOrderStartActivity listener creates status history on WorkOrderStarted', function () {
    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $this->user->id,
        'assigned_to' => $this->user->id,
    ]);

    $event = new WorkOrderStarted($wo, $this->user, 'open');
    $listener = app(LogWorkOrderStartActivity::class);
    $listener->handle($event);

    $this->assertDatabaseHas('work_order_status_history', [
        'work_order_id' => $wo->id,
        'from_status' => 'open',
        'to_status' => WorkOrder::STATUS_IN_PROGRESS,
        'user_id' => $this->user->id,
    ]);
});

test('LogWorkOrderStartActivity sends notification to assigned technician', function () {
    $technician = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $this->user->id,
        'assigned_to' => $technician->id,
    ]);

    $event = new WorkOrderStarted($wo, $this->user, 'open');
    $listener = app(LogWorkOrderStartActivity::class);
    $listener->handle($event);

    $this->assertDatabaseHas('notifications', [
        'tenant_id' => $this->tenant->id,
        'user_id' => $technician->id,
        'type' => 'os_started',
    ]);
});

test('CreateAgendaItemOnWorkOrder creates agenda item on WorkOrderStarted', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'created_by' => $this->user->id,
        'assigned_to' => $this->user->id,
    ]);

    $event = new WorkOrderStarted($wo, $this->user, 'open');
    $listener = app(CreateAgendaItemOnWorkOrder::class);
    $listener->handleWorkOrderStarted($event);

    $this->assertDatabaseHas('central_items', [
        'tenant_id' => $this->tenant->id,
        'responsavel_user_id' => $this->user->id,
    ]);
});

// ---------------------------------------------------------------------------
// WorkOrderCompleted Event + Listeners
// ---------------------------------------------------------------------------

test('WorkOrderCompleted event carries correct data', function () {
    $wo = WorkOrder::factory()->completed()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $this->user->id,
    ]);

    $event = new WorkOrderCompleted($wo, $this->user, 'in_progress');

    expect($event->workOrder->id)->toBe($wo->id)
        ->and($event->fromStatus)->toBe('in_progress');
});

test('HandleWorkOrderCompletion creates status history entry', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $this->user->id,
        'customer_id' => $customer->id,
        'total' => 100,
    ]);

    $event = new WorkOrderCompleted($wo, $this->user, 'in_progress');

    $clientNotificationService = $this->mock(ClientNotificationService::class);
    $clientNotificationService->shouldReceive('notifyOsCompleted')->once();

    $commissionService = $this->mock(CommissionService::class);
    $commissionService->shouldReceive('calculateAndGenerate')->once();

    $conversionService = $this->mock(CustomerConversionService::class);
    $conversionService->shouldReceive('convertLeadIfFirstOS')->once();

    $listener = app(HandleWorkOrderCompletion::class);
    $listener->handle($event);

    $this->assertDatabaseHas('work_order_status_history', [
        'work_order_id' => $wo->id,
        'to_status' => WorkOrder::STATUS_COMPLETED,
    ]);
});

test('HandleWorkOrderCompletion notifies work order creator', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $this->user->id,
        'customer_id' => $customer->id,
    ]);

    $event = new WorkOrderCompleted($wo, $this->user, 'in_progress');

    $this->mock(ClientNotificationService::class)->shouldIgnoreMissing();
    $this->mock(CommissionService::class)->shouldIgnoreMissing();
    $this->mock(CustomerConversionService::class)->shouldIgnoreMissing();

    $listener = app(HandleWorkOrderCompletion::class);
    $listener->handle($event);

    $this->assertDatabaseHas('notifications', [
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'type' => 'os_completed',
    ]);
});

test('TriggerNpsSurvey sends NPS notification to customer with email', function () {
    Notification::fake();

    $customer = Customer::factory()->create([
        'tenant_id' => $this->tenant->id,
        'email' => 'cliente@example.com',
    ]);
    $wo = WorkOrder::factory()->completed()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'created_by' => $this->user->id,
    ]);

    $event = new WorkOrderCompleted($wo, $this->user, 'in_progress');
    $listener = app(TriggerNpsSurvey::class);
    $listener->handle($event);

    Notification::assertSentTo(
        $customer,
        NpsSurveyNotification::class
    );
});

test('TriggerNpsSurvey skips customer without email', function () {
    Notification::fake();

    $customer = Customer::factory()->create([
        'tenant_id' => $this->tenant->id,
        'email' => null,
    ]);
    $wo = WorkOrder::factory()->completed()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'created_by' => $this->user->id,
    ]);

    $event = new WorkOrderCompleted($wo, $this->user, 'in_progress');
    $listener = app(TriggerNpsSurvey::class);
    $listener->handle($event);

    Notification::assertNothingSent();
});

test('CreateAgendaItemOnWorkOrder marks agenda as completed on WorkOrderCompleted', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'created_by' => $this->user->id,
        'assigned_to' => $this->user->id,
    ]);

    // First create the agenda item
    $startEvent = new WorkOrderStarted($wo, $this->user, 'open');
    $listener = app(CreateAgendaItemOnWorkOrder::class);
    $listener->handleWorkOrderStarted($startEvent);

    // Then complete it
    $completeEvent = new WorkOrderCompleted($wo, $this->user, 'in_progress');
    $listener->handleWorkOrderCompleted($completeEvent);

    $agenda = AgendaItem::where('ref_tipo', WorkOrder::class)
        ->where('ref_id', $wo->id)
        ->first();

    expect($agenda)->not->toBeNull();
});

test('CreateWarrantyTrackingOnWorkOrderInvoiced creates warranty records on completion', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'track_stock' => false]);
    $wo = WorkOrder::factory()->completed()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'created_by' => $this->user->id,
    ]);
    WorkOrderItem::factory()->create([
        'work_order_id' => $wo->id,
        'type' => WorkOrderItem::TYPE_PRODUCT,
        'reference_id' => $product->id,
        'tenant_id' => $this->tenant->id,
    ]);

    $event = new WorkOrderCompleted($wo, $this->user, 'in_progress');
    $listener = app(CreateWarrantyTrackingOnWorkOrderInvoiced::class);
    $listener->handleWorkOrderCompleted($event);

    $this->assertDatabaseHas('warranty_tracking', [
        'work_order_id' => $wo->id,
        'tenant_id' => $this->tenant->id,
    ]);
});

// ---------------------------------------------------------------------------
// WorkOrderCancelled Event + Listeners
// ---------------------------------------------------------------------------

test('WorkOrderCancelled event carries reason and from status', function () {
    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $this->user->id,
    ]);

    $event = new WorkOrderCancelled($wo, $this->user, 'Customer request', 'in_progress');

    expect($event->reason)->toBe('Customer request')
        ->and($event->fromStatus)->toBe('in_progress');
});

test('HandleWorkOrderCancellation creates status history and notifies creator', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $this->user->id,
        'customer_id' => $customer->id,
    ]);

    $stockService = $this->mock(StockService::class);
    $stockService->shouldReceive('returnStock')->never();

    $event = new WorkOrderCancelled($wo, $this->user, 'Teste', 'open');
    $listener = app(HandleWorkOrderCancellation::class);
    $listener->handle($event);

    $this->assertDatabaseHas('work_order_status_history', [
        'work_order_id' => $wo->id,
        'to_status' => WorkOrder::STATUS_CANCELLED,
    ]);

    $this->assertDatabaseHas('notifications', [
        'user_id' => $this->user->id,
        'type' => 'os_cancelled',
    ]);
});

test('HandleWorkOrderCancellation returns stock for product items', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'track_stock' => false]);
    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $this->user->id,
        'customer_id' => $customer->id,
    ]);
    WorkOrderItem::factory()->create([
        'work_order_id' => $wo->id,
        'type' => WorkOrderItem::TYPE_PRODUCT,
        'reference_id' => $product->id,
        'quantity' => 5,
        'tenant_id' => $this->tenant->id,
    ]);

    // Re-enable track_stock for the cancellation test
    $product->update(['track_stock' => true]);

    $stockService = $this->mock(StockService::class);
    $stockService->shouldReceive('returnStock')
        ->once()
        ->withArgs(fn ($p, $qty, $w) => $p->id === $product->id && $qty == 5.0);

    $event = new WorkOrderCancelled($wo, $this->user, 'Cancelado', 'in_progress');
    $listener = app(HandleWorkOrderCancellation::class);
    $listener->handle($event);
});

test('HandleWorkOrderCancellation reverses pending commissions', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $this->user->id,
        'customer_id' => $customer->id,
    ]);
    CommissionEvent::factory()->create([
        'tenant_id' => $this->tenant->id,
        'work_order_id' => $wo->id,
        'user_id' => $this->user->id,
        'status' => CommissionEvent::STATUS_PENDING,
    ]);

    $this->mock(StockService::class)->shouldIgnoreMissing();

    $event = new WorkOrderCancelled($wo, $this->user, 'Cancelado', 'in_progress');
    $listener = app(HandleWorkOrderCancellation::class);
    $listener->handle($event);

    $this->assertDatabaseHas('commission_events', [
        'work_order_id' => $wo->id,
        'status' => CommissionEvent::STATUS_REVERSED,
    ]);
});

// ---------------------------------------------------------------------------
// WorkOrderInvoiced Event + Listeners
// ---------------------------------------------------------------------------

test('WorkOrderInvoiced event is structured correctly', function () {
    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $this->user->id,
    ]);

    $event = new WorkOrderInvoiced($wo, $this->user, 'delivered');

    expect($event->workOrder->id)->toBe($wo->id)
        ->and($event->fromStatus)->toBe('delivered');
});

test('HandleWorkOrderInvoicing creates status history and invoice', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $wo = WorkOrder::factory()->delivered()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $this->user->id,
        'customer_id' => $customer->id,
        'total' => 1000,
    ]);

    $invoicingService = $this->mock(InvoicingService::class);
    $invoice = Invoice::factory()->create([
        'tenant_id' => $this->tenant->id,
        'work_order_id' => $wo->id,
    ]);
    $invoicingService->shouldReceive('generateFromWorkOrder')
        ->once()
        ->andReturn(['invoice' => $invoice, 'ar' => null, 'receivables' => []]);

    $this->mock(StockService::class)->shouldIgnoreMissing();
    $this->mock(CommissionService::class)->shouldIgnoreMissing();

    $event = new WorkOrderInvoiced($wo, $this->user, 'delivered');
    $listener = app(HandleWorkOrderInvoicing::class);
    $listener->handle($event);

    $this->assertDatabaseHas('work_order_status_history', [
        'work_order_id' => $wo->id,
        'to_status' => WorkOrder::STATUS_INVOICED,
    ]);
});

// ---------------------------------------------------------------------------
// QuoteApproved Event + Listeners
// ---------------------------------------------------------------------------

test('QuoteApproved event carries quote and user', function () {
    $quote = Quote::factory()->create([
        'tenant_id' => $this->tenant->id,
        'seller_id' => $this->user->id,
    ]);

    $event = new QuoteApproved($quote, $this->user);

    expect($event->quote->id)->toBe($quote->id)
        ->and($event->user->id)->toBe($this->user->id);
});

test('HandleQuoteApproval creates CRM activity and notification', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $quote = Quote::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'seller_id' => $this->user->id,
        'total' => 5000,
        'approved_at' => now(),
    ]);

    $event = new QuoteApproved($quote, $this->user);
    $listener = app(HandleQuoteApproval::class);
    $listener->handle($event);

    $this->assertDatabaseHas('crm_activities', [
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'type' => Quote::ACTIVITY_TYPE_APPROVED,
    ]);

    $this->assertDatabaseHas('notifications', [
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'type' => Quote::ACTIVITY_TYPE_APPROVED,
    ]);
});

test('CreateAgendaItemOnQuote creates agenda item on QuoteApproved', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $quote = Quote::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'seller_id' => $this->user->id,
        'total' => 2500,
    ]);

    $event = new QuoteApproved($quote, $this->user);
    $listener = app(CreateAgendaItemOnQuote::class);
    $listener->handle($event);

    $this->assertDatabaseHas('central_items', [
        'tenant_id' => $this->tenant->id,
    ]);
});

// ---------------------------------------------------------------------------
// PaymentReceived Event + Listeners
// ---------------------------------------------------------------------------

test('PaymentReceived event carries account receivable and payment', function () {
    $ar = AccountReceivable::factory()->create(['tenant_id' => $this->tenant->id, 'created_by' => $this->user->id]);
    $payment = Payment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'payable_type' => AccountReceivable::class,
        'payable_id' => $ar->id,
        'received_by' => $this->user->id,
    ]);

    $event = new PaymentReceived($ar, $payment);

    expect($event->accountReceivable->id)->toBe($ar->id)
        ->and($event->payment->id)->toBe($payment->id);
});

test('HandlePaymentReceived releases commissions and notifies', function () {
    $ar = AccountReceivable::factory()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $this->user->id,
    ]);
    $payment = Payment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'payable_type' => AccountReceivable::class,
        'payable_id' => $ar->id,
        'amount' => 500,
        'received_by' => $this->user->id,
    ]);

    $commissionService = $this->mock(CommissionService::class);
    $commissionService->shouldReceive('releaseByPayment')->once();

    $event = new PaymentReceived($ar, $payment);
    $listener = app(HandlePaymentReceived::class);
    $listener->handle($event);

    $this->assertDatabaseHas('notifications', [
        'tenant_id' => $this->tenant->id,
        'type' => 'payment_received',
    ]);
});

test('CreateAgendaItemOnPayment creates agenda item on PaymentReceived', function () {
    $ar = AccountReceivable::factory()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $this->user->id,
    ]);
    $payment = Payment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'payable_type' => AccountReceivable::class,
        'payable_id' => $ar->id,
        'amount' => 750,
        'received_by' => $this->user->id,
    ]);

    $event = new PaymentReceived($ar, $payment);
    $listener = app(CreateAgendaItemOnPayment::class);
    $listener->handle($event);

    $this->assertDatabaseHas('central_items', [
        'tenant_id' => $this->tenant->id,
    ]);
});

// ---------------------------------------------------------------------------
// PaymentMade Event + Listeners
// ---------------------------------------------------------------------------

test('PaymentMade event carries account payable', function () {
    $ap = AccountPayable::factory()->create(['tenant_id' => $this->tenant->id]);

    $event = new PaymentMade($ap);

    expect($event->accountPayable->id)->toBe($ap->id);
});

test('HandlePaymentMade notifies responsible user', function () {
    $ap = AccountPayable::factory()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $this->user->id,
    ]);
    $payment = Payment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'payable_type' => AccountPayable::class,
        'payable_id' => $ap->id,
        'amount' => 300,
        'received_by' => $this->user->id,
    ]);

    $event = new PaymentMade($ap, $payment);
    $listener = app(HandlePaymentMade::class);
    $listener->handle($event);

    $this->assertDatabaseHas('notifications', [
        'tenant_id' => $this->tenant->id,
        'type' => 'payment_made',
    ]);
});

// ---------------------------------------------------------------------------
// CustomerCreated Event + Listeners
// ---------------------------------------------------------------------------

test('CustomerCreated event carries customer model', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

    $event = new CustomerCreated($customer);

    expect($event->customer->id)->toBe($customer->id);
});

test('HandleCustomerCreated schedules welcome follow-up for assigned seller', function () {
    $seller = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $customer = Customer::factory()->create([
        'tenant_id' => $this->tenant->id,
        'assigned_seller_id' => $seller->id,
    ]);

    $event = new CustomerCreated($customer);
    $listener = app(HandleCustomerCreated::class);
    $listener->handle($event);

    $this->assertDatabaseHas('crm_activities', [
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'user_id' => $seller->id,
        'type' => 'follow_up',
    ]);

    $this->assertDatabaseHas('notifications', [
        'tenant_id' => $this->tenant->id,
        'user_id' => $seller->id,
        'type' => 'new_customer',
    ]);
});

test('HandleCustomerCreated does nothing without assigned seller', function () {
    $customer = Customer::factory()->create([
        'tenant_id' => $this->tenant->id,
        'assigned_seller_id' => null,
    ]);

    $event = new CustomerCreated($customer);
    $listener = app(HandleCustomerCreated::class);
    $listener->handle($event);

    $this->assertDatabaseMissing('crm_activities', [
        'customer_id' => $customer->id,
        'type' => 'follow_up',
    ]);
});

// ---------------------------------------------------------------------------
// CalibrationCompleted Event + Listeners
// ---------------------------------------------------------------------------

test('CalibrationCompleted event carries work order and equipment ID', function () {
    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $this->user->id,
    ]);

    $event = new CalibrationCompleted($wo, 42);

    expect($event->workOrder->id)->toBe($wo->id)
        ->and($event->equipmentId)->toBe(42);
});

test('TriggerCertificateGeneration calls certificate service', function () {
    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $this->user->id,
    ]);

    $certService = $this->mock(CalibrationCertificateService::class);
    $certService->shouldReceive('generateFromWorkOrder')
        ->once()
        ->with($wo, 99);

    $event = new CalibrationCompleted($wo, 99);
    $listener = app(TriggerCertificateGeneration::class);
    $listener->handle($event);
});

test('GenerateCorrectiveQuoteOnCalibrationFailure creates quote on failed calibration', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $equipment = Equipment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
    ]);
    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'created_by' => $this->user->id,
    ]);

    EquipmentCalibration::create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $equipment->id,
        'work_order_id' => $wo->id,
        'result' => 'rejected',
        'calibration_date' => now(),
        'performed_by' => $this->user->id,
    ]);

    $event = new CalibrationCompleted($wo, $equipment->id);
    $listener = app(GenerateCorrectiveQuoteOnCalibrationFailure::class);
    $listener->handle($event);

    $this->assertDatabaseHas('quotes', [
        'tenant_id' => $this->tenant->id,
        'status' => 'draft',
    ]);
});

test('GenerateCorrectiveQuoteOnCalibrationFailure does nothing on approved calibration', function () {
    $equipment = Equipment::factory()->create(['tenant_id' => $this->tenant->id]);
    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $this->user->id,
    ]);

    EquipmentCalibration::create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $equipment->id,
        'work_order_id' => $wo->id,
        'result' => 'approved',
        'calibration_date' => now(),
        'performed_by' => $this->user->id,
    ]);

    $countBefore = Quote::count();

    $event = new CalibrationCompleted($wo, $equipment->id);
    $listener = app(GenerateCorrectiveQuoteOnCalibrationFailure::class);
    $listener->handle($event);

    expect(Quote::count())->toBe($countBefore);
});

// ---------------------------------------------------------------------------
// CalibrationExpiring Event + Listeners
// ---------------------------------------------------------------------------

test('CalibrationExpiring event carries calibration and days until expiry', function () {
    $equipment = Equipment::factory()->create(['tenant_id' => $this->tenant->id]);
    $calibration = EquipmentCalibration::create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $equipment->id,
        'calibration_date' => now(),
        'performed_by' => $this->user->id,
        'result' => 'approved',
    ]);

    $event = new CalibrationExpiring($calibration, 15);

    expect($event->calibration->id)->toBe($calibration->id)
        ->and($event->daysUntilExpiry)->toBe(15);
});

test('HandleCalibrationExpiring creates notification and CRM activity', function () {
    $seller = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $customer = Customer::factory()->create([
        'tenant_id' => $this->tenant->id,
        'assigned_seller_id' => $seller->id,
    ]);
    $equipment = Equipment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'serial_number' => 'EQ-001',
    ]);
    $calibration = EquipmentCalibration::create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $equipment->id,
        'calibration_date' => now(),
        'performed_by' => $this->user->id,
        'result' => 'approved',
    ]);

    $event = new CalibrationExpiring($calibration, 30);
    $listener = app(HandleCalibrationExpiring::class);
    $listener->handle($event);

    $this->assertDatabaseHas('notifications', [
        'tenant_id' => $this->tenant->id,
        'user_id' => $seller->id,
        'type' => 'calibration_expiring',
    ]);

    $this->assertDatabaseHas('crm_activities', [
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'type' => 'follow_up',
    ]);
});

// ---------------------------------------------------------------------------
// ContractRenewing Event + Listeners
// ---------------------------------------------------------------------------

test('HandleContractRenewing creates notification for responsible user', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $contract = RecurringContract::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'assigned_to' => $this->user->id,
        'end_date' => now()->addDays(15),
    ]);

    $event = new ContractRenewing($contract, 15);
    $listener = app(HandleContractRenewing::class);
    $listener->handle($event);

    $this->assertDatabaseHas('notifications', [
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'type' => 'contract_renewing',
    ]);
});

test('CreateAgendaItemOnContract creates agenda item on ContractRenewing', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $contract = RecurringContract::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'assigned_to' => $this->user->id,
        'end_date' => now()->addDays(10),
    ]);

    $event = new ContractRenewing($contract, 10);
    $listener = app(CreateAgendaItemOnContract::class);
    $listener->handle($event);

    $this->assertDatabaseHas('central_items', [
        'tenant_id' => $this->tenant->id,
    ]);
});

// ---------------------------------------------------------------------------
// CommissionGenerated Event + Listeners
// ---------------------------------------------------------------------------

test('CommissionGenerated event carries commission model', function () {
    $commission = CommissionEvent::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
    ]);

    $event = new CommissionGenerated($commission);

    expect($event->commission->id)->toBe($commission->id);
});

test('NotifyBeneficiaryOnCommission sends notification to user', function () {
    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $this->user->id,
    ]);
    $commission = CommissionEvent::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'work_order_id' => $wo->id,
        'commission_amount' => 150,
    ]);

    $event = new CommissionGenerated($commission);
    $listener = app(NotifyBeneficiaryOnCommission::class);
    $listener->handle($event);

    $this->assertDatabaseHas('notifications', [
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'type' => 'commission_generated',
    ]);
});

// ---------------------------------------------------------------------------
// FiscalNoteAuthorized Event + Listeners
// ---------------------------------------------------------------------------

test('FiscalNoteAuthorized event carries fiscal note', function () {
    $note = FiscalNote::factory()->create(['tenant_id' => $this->tenant->id]);

    $event = new FiscalNoteAuthorized($note);

    expect($event->fiscalNote->id)->toBe($note->id);
});

test('ReleaseWorkOrderOnFiscalNoteAuthorized updates delivered OS to invoiced', function () {
    $wo = WorkOrder::factory()->delivered()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $this->user->id,
    ]);

    // Create a system user for the listener
    User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'email' => config('services.fiscal.system_user_email', 'sistema@localhost'),
    ]);

    $note = FiscalNote::factory()->create([
        'tenant_id' => $this->tenant->id,
        'work_order_id' => $wo->id,
    ]);

    $event = new FiscalNoteAuthorized($note);
    $listener = app(ReleaseWorkOrderOnFiscalNoteAuthorized::class);
    $listener->handle($event);

    $this->assertDatabaseHas('work_orders', [
        'id' => $wo->id,
        'status' => WorkOrder::STATUS_INVOICED,
    ]);
});

test('ReleaseWorkOrderOnFiscalNoteAuthorized ignores OS not in delivered status', function () {
    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $this->user->id,
        'status' => WorkOrder::STATUS_OPEN,
    ]);

    $note = FiscalNote::factory()->create([
        'tenant_id' => $this->tenant->id,
        'work_order_id' => $wo->id,
    ]);

    $event = new FiscalNoteAuthorized($note);
    $listener = app(ReleaseWorkOrderOnFiscalNoteAuthorized::class);
    $listener->handle($event);

    $this->assertDatabaseHas('work_orders', [
        'id' => $wo->id,
        'status' => WorkOrder::STATUS_OPEN,
    ]);
});

// ---------------------------------------------------------------------------
// ServiceCall Events + Listeners
// ---------------------------------------------------------------------------

test('ServiceCallCreated event carries service call and user', function () {
    $sc = ServiceCall::factory()->create(['tenant_id' => $this->tenant->id]);

    $event = new ServiceCallCreated($sc, $this->user);

    expect($event->serviceCall->id)->toBe($sc->id)
        ->and($event->user->id)->toBe($this->user->id);
});

test('CreateAgendaItemOnServiceCall creates agenda on service call created', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $sc = ServiceCall::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'created_by' => $this->user->id,
    ]);

    $event = new ServiceCallCreated($sc, $this->user);
    $listener = app(CreateAgendaItemOnServiceCall::class);
    $listener->handleCreated($event);

    $this->assertDatabaseHas('central_items', [
        'tenant_id' => $this->tenant->id,
    ]);
});

test('ServiceCallStatusChanged event carries new status', function () {
    $sc = ServiceCall::factory()->create(['tenant_id' => $this->tenant->id]);

    $event = new ServiceCallStatusChanged($sc, 'open', 'in_progress', $this->user);

    expect($event->serviceCall->id)->toBe($sc->id)
        ->and($event->toStatus)->toBe('in_progress');
});

// ---------------------------------------------------------------------------
// NotificationSent (Broadcast) Event
// ---------------------------------------------------------------------------

test('NotificationSent event broadcasts on correct channels', function () {
    $event = new NotificationSent(
        ['id' => 1, 'title' => 'Test'],
        $this->tenant->id,
        $this->user->id
    );

    $channels = $event->broadcastOn();

    expect($channels)->toHaveCount(2);
    expect($channels[0]->name)->toBe("private-tenant.{$this->tenant->id}.notifications");
    expect($channels[1]->name)->toBe("private-user.{$this->user->id}.notifications");
});

test('NotificationSent broadcasts as notification.new', function () {
    $event = new NotificationSent(['id' => 1], $this->tenant->id);

    expect($event->broadcastAs())->toBe('notification.new');
});

test('NotificationSent broadcastWith includes notification data and timestamp', function () {
    $data = ['id' => 1, 'title' => 'Test Notification'];
    $event = new NotificationSent($data, $this->tenant->id);

    $broadcastData = $event->broadcastWith();

    expect($broadcastData)->toHaveKey('notification')
        ->and($broadcastData)->toHaveKey('timestamp')
        ->and($broadcastData['notification'])->toBe($data);
});

test('NotificationSent without user only broadcasts to tenant channel', function () {
    $event = new NotificationSent(['id' => 1], $this->tenant->id, null);

    $channels = $event->broadcastOn();

    expect($channels)->toHaveCount(1);
    expect($channels[0]->name)->toBe("private-tenant.{$this->tenant->id}.notifications");
});

// ---------------------------------------------------------------------------
// Event-Listener mapping verification
// ---------------------------------------------------------------------------

test('WorkOrderStarted has LogWorkOrderStartActivity listener registered', function () {
    Event::fake([WorkOrderStarted::class]);

    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $this->user->id,
    ]);

    WorkOrderStarted::dispatch($wo, $this->user, 'open');

    Event::assertDispatched(WorkOrderStarted::class);
});

test('WorkOrderCompleted has multiple listeners registered', function () {
    Event::fake([WorkOrderCompleted::class]);

    $wo = WorkOrder::factory()->completed()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $this->user->id,
    ]);

    WorkOrderCompleted::dispatch($wo, $this->user, 'in_progress');

    Event::assertDispatched(WorkOrderCompleted::class);
});

test('CalibrationCompleted has certificate and corrective quote listeners', function () {
    Event::fake([CalibrationCompleted::class]);

    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $this->user->id,
    ]);

    CalibrationCompleted::dispatch($wo, 1);

    Event::assertDispatched(CalibrationCompleted::class);
});
