<?php

use App\Mail\QuoteReadyMail;
use App\Mail\WorkOrderStatusMail;
use App\Models\Customer;
use App\Models\Quote;
use App\Models\SystemSetting;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\ClientNotificationService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app()->instance('current_tenant_id', $this->tenant->id);

    $this->user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'current_tenant_id' => $this->tenant->id,
    ]);

    $this->customer = Customer::factory()->create([
        'tenant_id' => $this->tenant->id,
        'email' => 'cliente@test.com',
        'phone' => '11999990000',
    ]);

    $this->service = app(ClientNotificationService::class);
});

test('notifyOsCreated does nothing when setting is disabled', function () {
    Mail::fake();

    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'created_by' => $this->user->id,
    ]);

    $this->service->notifyOsCreated($wo);

    Mail::assertNothingQueued();
});

test('notifyOsCreated sends email when setting is enabled', function () {
    Mail::fake();

    SystemSetting::create([
        'tenant_id' => $this->tenant->id,
        'key' => 'notify_client_os_created',
        'value' => '1',
    ]);

    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'created_by' => $this->user->id,
    ]);

    $this->service->notifyOsCreated($wo);

    Mail::assertQueued(WorkOrderStatusMail::class);
});

test('notifyOsCreated does not send email when customer has no email', function () {
    Mail::fake();

    SystemSetting::create([
        'tenant_id' => $this->tenant->id,
        'key' => 'notify_client_os_created',
        'value' => '1',
    ]);

    $customerNoEmail = Customer::factory()->create([
        'tenant_id' => $this->tenant->id,
        'email' => null,
        'phone' => null,
    ]);

    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customerNoEmail->id,
        'created_by' => $this->user->id,
    ]);

    $this->service->notifyOsCreated($wo);

    Mail::assertNothingQueued();
});

test('notifyOsCompleted sends email when enabled', function () {
    Mail::fake();

    SystemSetting::create([
        'tenant_id' => $this->tenant->id,
        'key' => 'notify_client_os_completed',
        'value' => '1',
    ]);

    $wo = WorkOrder::factory()->completed()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'created_by' => $this->user->id,
    ]);

    $this->service->notifyOsCompleted($wo);

    Mail::assertQueued(WorkOrderStatusMail::class);
});

test('notifyOsAwaitingApproval sends email when enabled', function () {
    Mail::fake();

    SystemSetting::create([
        'tenant_id' => $this->tenant->id,
        'key' => 'notify_client_os_awaiting',
        'value' => '1',
    ]);

    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'created_by' => $this->user->id,
        'status' => WorkOrder::STATUS_WAITING_APPROVAL,
    ]);

    $this->service->notifyOsAwaitingApproval($wo);

    Mail::assertQueued(WorkOrderStatusMail::class);
});

test('notifyOsCreated sends WhatsApp when enabled and configured', function () {
    Http::fake(['*' => Http::response(['success' => true], 200)]);

    SystemSetting::create(['tenant_id' => $this->tenant->id, 'key' => 'notify_client_os_created', 'value' => '1']);
    SystemSetting::create(['tenant_id' => $this->tenant->id, 'key' => 'whatsapp_enabled', 'value' => '1']);
    SystemSetting::create(['tenant_id' => $this->tenant->id, 'key' => 'evolution_api_url', 'value' => 'https://api.evolution.test']);
    SystemSetting::create(['tenant_id' => $this->tenant->id, 'key' => 'evolution_api_key', 'value' => 'test-api-key']);
    SystemSetting::create(['tenant_id' => $this->tenant->id, 'key' => 'evolution_instance', 'value' => 'test-instance']);

    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'created_by' => $this->user->id,
    ]);

    $this->service->notifyOsCreated($wo);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'message/sendText');
    });
});

test('WhatsApp not sent when not configured', function () {
    Http::fake();

    SystemSetting::create(['tenant_id' => $this->tenant->id, 'key' => 'notify_client_os_created', 'value' => '1']);
    // WhatsApp NOT configured

    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'created_by' => $this->user->id,
    ]);

    $this->service->notifyOsCreated($wo);

    Http::assertNothingSent();
});

test('alertOsWithoutBilling does nothing when disabled', function () {
    Http::fake();

    $wo = WorkOrder::factory()->completed()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'created_by' => $this->user->id,
    ]);

    $this->service->alertOsWithoutBilling($wo);

    Http::assertNothingSent();
});

test('notifyQuoteReady sends email to customer when enabled', function () {
    Mail::fake();

    SystemSetting::create([
        'tenant_id' => $this->tenant->id,
        'key' => 'notify_client_quote_ready',
        'value' => '1',
    ]);

    $quote = Quote::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'seller_id' => $this->user->id,
        'status' => 'sent',
        'total' => 5000.00,
    ]);

    $this->service->notifyQuoteReady($quote);

    Mail::assertQueued(QuoteReadyMail::class);
});
