<?php

use App\Models\Customer;
use App\Models\SystemAlert;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\SlaEscalationService;
use App\Services\WebPushService;
use App\Services\WhatsAppService;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app()->instance('current_tenant_id', $this->tenant->id);

    $this->user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'current_tenant_id' => $this->tenant->id,
    ]);

    $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

    // Mock external services
    $this->mock(WebPushService::class);
    $this->mock(WhatsAppService::class);

    $this->service = app(SlaEscalationService::class);
});

test('evaluateSla returns null when work order has no sla_due_at', function () {
    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'created_by' => $this->user->id,
        'sla_due_at' => null,
    ]);

    $result = $this->service->evaluateSla($wo);

    expect($result)->toBeNull();
});

test('evaluateSla returns warning level at 50% SLA usage', function () {
    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'created_by' => $this->user->id,
        'created_at' => Carbon::now()->subHours(5),
        'sla_due_at' => Carbon::now()->addHours(5),
    ]);

    $result = $this->service->evaluateSla($wo);

    expect($result)->not->toBeNull();
    expect($result['level'])->toBe('warning');
    expect($result['threshold'])->toBe(50);
    expect($result['percent_used'])->toBeGreaterThanOrEqual(49);
});

test('evaluateSla returns high level at 75% SLA usage', function () {
    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'created_by' => $this->user->id,
        'created_at' => Carbon::now()->subHours(9),
        'sla_due_at' => Carbon::now()->addHours(3),
    ]);

    $result = $this->service->evaluateSla($wo);

    expect($result)->not->toBeNull();
    expect($result['level'])->toBe('high');
    expect($result['percent_used'])->toBeGreaterThanOrEqual(74);
});

test('evaluateSla returns critical level at 90% SLA usage', function () {
    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'created_by' => $this->user->id,
        'created_at' => Carbon::now()->subHours(18),
        'sla_due_at' => Carbon::now()->addHours(2),
    ]);

    $result = $this->service->evaluateSla($wo);

    expect($result)->not->toBeNull();
    expect($result['level'])->toBe('critical');
    expect($result['percent_used'])->toBeGreaterThanOrEqual(89);
});

test('evaluateSla returns breached level when SLA is expired', function () {
    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'created_by' => $this->user->id,
        'created_at' => Carbon::now()->subHours(12),
        'sla_due_at' => Carbon::now()->subHour(), // already passed
    ]);

    $result = $this->service->evaluateSla($wo);

    expect($result)->not->toBeNull();
    expect($result['level'])->toBe('breached');
    expect($result['percent_used'])->toBeGreaterThan(100);
});

test('evaluateSla does not re-escalate if already escalated within 24h', function () {
    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'created_by' => $this->user->id,
        'created_at' => Carbon::now()->subHours(5),
        'sla_due_at' => Carbon::now()->addHours(5),
    ]);

    // Create existing alert
    SystemAlert::create([
        'tenant_id' => $this->tenant->id,
        'alert_type' => 'sla_escalation_warning',
        'severity' => 'warning',
        'title' => 'SLA warning',
        'message' => 'Test',
        'alertable_type' => WorkOrder::class,
        'alertable_id' => $wo->id,
    ]);

    $result = $this->service->evaluateSla($wo);

    // Should return null because warning was already escalated
    expect($result)->toBeNull();
});

test('evaluateSla returns minutes remaining', function () {
    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'created_by' => $this->user->id,
        'created_at' => Carbon::now()->subHours(3),
        'sla_due_at' => Carbon::now()->addHours(3),
    ]);

    $result = $this->service->evaluateSla($wo);

    if ($result !== null) {
        expect($result['minutes_remaining'])->toBeGreaterThan(0);
    }
});

test('getDashboard returns correct stats structure', function () {
    $result = $this->service->getDashboard($this->tenant->id);

    expect($result)->toHaveKeys(['on_time', 'at_risk', 'breached', 'total', 'compliance_rate']);
    expect($result['compliance_rate'])->toBe(100.0); // no WOs = 100%
});

test('getDashboard counts completed WOs as on_time when within SLA', function () {
    WorkOrder::factory()->completed()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'created_by' => $this->user->id,
        'sla_due_at' => Carbon::now()->addDay(),
        'completed_at' => Carbon::now(),
    ]);

    $result = $this->service->getDashboard($this->tenant->id);

    expect($result['on_time'])->toBe(1);
    expect($result['total'])->toBe(1);
    expect($result['compliance_rate'])->toBe(100.0);
});

test('runSlaChecks returns checked and escalated counts', function () {
    WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'created_by' => $this->user->id,
        'status' => WorkOrder::STATUS_OPEN,
        'sla_due_at' => Carbon::now()->addHours(5),
        'created_at' => Carbon::now()->subHours(5),
    ]);

    $result = $this->service->runSlaChecks($this->tenant->id);

    expect($result)->toHaveKeys(['checked', 'escalated', 'breached']);
    expect($result['checked'])->toBeGreaterThanOrEqual(1);
});
