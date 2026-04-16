<?php

use App\Events\NotificationSent;
use App\Events\ReconciliationUpdated;
use App\Events\ServiceCallStatusChanged;
use App\Events\TechnicianLocationUpdated;
use App\Events\WorkOrderStatusChanged;
use App\Models\Customer;
use App\Models\ServiceCall;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Support\Facades\Event;
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
// WorkOrderStatusChanged (ShouldBroadcastNow)
// ---------------------------------------------------------------------------

test('WorkOrderStatusChanged broadcasts on dashboard private channel', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'created_by' => $this->user->id,
        'assigned_to' => $this->user->id,
        'status' => WorkOrder::STATUS_IN_PROGRESS,
    ]);

    $event = new WorkOrderStatusChanged($wo);
    $channels = $event->broadcastOn();

    expect($channels)->toHaveCount(1);
    expect($channels[0])->toBeInstanceOf(PrivateChannel::class);
    expect($channels[0]->name)->toBe("private-dashboard.{$this->tenant->id}");
});

test('WorkOrderStatusChanged broadcasts as work_order.status.changed', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'created_by' => $this->user->id,
    ]);

    $event = new WorkOrderStatusChanged($wo);

    expect($event->broadcastAs())->toBe('work_order.status.changed');
});

test('WorkOrderStatusChanged includes work order data in broadcast', function () {
    $customer = Customer::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Acme Corp',
    ]);
    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'created_by' => $this->user->id,
        'assigned_to' => $this->user->id,
        'status' => WorkOrder::STATUS_COMPLETED,
    ]);

    $event = new WorkOrderStatusChanged($wo);

    expect($event->workOrder)->toHaveKey('id')
        ->and($event->workOrder)->toHaveKey('status')
        ->and($event->workOrder)->toHaveKey('customer')
        ->and($event->workOrder)->toHaveKey('technician')
        ->and($event->workOrder)->toHaveKey('tenant_id')
        ->and($event->workOrder['id'])->toBe($wo->id)
        ->and($event->workOrder['tenant_id'])->toBe($this->tenant->id);
});

test('WorkOrderStatusChanged implements ShouldBroadcastNow', function () {
    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $this->user->id,
    ]);

    $event = new WorkOrderStatusChanged($wo);

    expect($event)->toBeInstanceOf(ShouldBroadcastNow::class);
});

// ---------------------------------------------------------------------------
// ServiceCallStatusChanged (ShouldBroadcastNow)
// ---------------------------------------------------------------------------

test('ServiceCallStatusChanged broadcasts on dashboard channel', function () {
    $sc = ServiceCall::factory()->create(['tenant_id' => $this->tenant->id]);

    $event = new ServiceCallStatusChanged($sc, 'open', 'in_progress');
    $channels = $event->broadcastOn();

    expect($channels)->toHaveCount(1);
    expect($channels[0]->name)->toBe("private-dashboard.{$this->tenant->id}");
});

test('ServiceCallStatusChanged broadcasts as service_call.status.changed', function () {
    $sc = ServiceCall::factory()->create(['tenant_id' => $this->tenant->id]);

    $event = new ServiceCallStatusChanged($sc);

    expect($event->broadcastAs())->toBe('service_call.status.changed');
});

test('ServiceCallStatusChanged includes service call data in broadcastWith', function () {
    $customer = Customer::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Test Customer',
    ]);
    $technician = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $sc = ServiceCall::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'technician_id' => $technician->id,
    ]);

    $event = new ServiceCallStatusChanged($sc);
    $data = $event->broadcastWith();

    expect($data)->toHaveKey('serviceCall')
        ->and($data['serviceCall'])->toHaveKey('id')
        ->and($data['serviceCall'])->toHaveKey('status')
        ->and($data['serviceCall'])->toHaveKey('customer')
        ->and($data['serviceCall'])->toHaveKey('technician')
        ->and($data['serviceCall']['tenant_id'])->toBe($this->tenant->id);
});

// ---------------------------------------------------------------------------
// TechnicianLocationUpdated (ShouldBroadcastNow)
// ---------------------------------------------------------------------------

test('TechnicianLocationUpdated broadcasts on dashboard channel', function () {
    $technician = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'location_lat' => -23.55,
        'location_lng' => -46.63,
        'status' => 'working',
    ]);

    $event = new TechnicianLocationUpdated($technician);
    $channels = $event->broadcastOn();

    expect($channels)->toHaveCount(1);
    expect($channels[0]->name)->toBe("private-dashboard.{$this->tenant->id}");
});

test('TechnicianLocationUpdated broadcasts as technician.location.updated', function () {
    $event = new TechnicianLocationUpdated($this->user);

    expect($event->broadcastAs())->toBe('technician.location.updated');
});

test('TechnicianLocationUpdated includes technician data', function () {
    $technician = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Joao Tecnico',
        'location_lat' => -23.55,
        'location_lng' => -46.63,
        'status' => 'in_transit',
    ]);

    $event = new TechnicianLocationUpdated($technician);

    expect($event->technician)->toHaveKey('id')
        ->and($event->technician)->toHaveKey('name')
        ->and($event->technician)->toHaveKey('status')
        ->and($event->technician)->toHaveKey('location_lat')
        ->and($event->technician)->toHaveKey('location_lng')
        ->and($event->technician['name'])->toBe('Joao Tecnico');
});

// ---------------------------------------------------------------------------
// ReconciliationUpdated (ShouldBroadcast)
// ---------------------------------------------------------------------------

test('ReconciliationUpdated broadcasts on tenant reconciliation channel', function () {
    $event = new ReconciliationUpdated($this->tenant->id, 'matched', ['total' => 5]);
    $channels = $event->broadcastOn();

    expect($channels)->toHaveCount(1);
    expect($channels[0]->name)->toBe("private-tenant.{$this->tenant->id}.reconciliation");
});

test('ReconciliationUpdated broadcasts as reconciliation.updated', function () {
    $event = new ReconciliationUpdated($this->tenant->id, 'imported');

    expect($event->broadcastAs())->toBe('reconciliation.updated');
});

test('ReconciliationUpdated includes action and summary in broadcast', function () {
    $summary = ['matched' => 10, 'unmatched' => 3];
    $event = new ReconciliationUpdated($this->tenant->id, 'processed', $summary);
    $data = $event->broadcastWith();

    expect($data)->toHaveKey('action')
        ->and($data)->toHaveKey('summary')
        ->and($data)->toHaveKey('timestamp')
        ->and($data['action'])->toBe('processed')
        ->and($data['summary'])->toBe($summary);
});

// ---------------------------------------------------------------------------
// NotificationSent (ShouldBroadcast)
// ---------------------------------------------------------------------------

test('NotificationSent broadcasts on tenant and user channels', function () {
    $event = new NotificationSent(
        ['title' => 'New OS'],
        $this->tenant->id,
        $this->user->id
    );

    $channels = $event->broadcastOn();

    expect($channels)->toHaveCount(2);

    $channelNames = array_map(fn ($c) => $c->name, $channels);
    expect($channelNames)->toContain("private-tenant.{$this->tenant->id}.notifications");
    expect($channelNames)->toContain("private-user.{$this->user->id}.notifications");
});

test('NotificationSent broadcasts only tenant channel when no user specified', function () {
    $event = new NotificationSent(['title' => 'Global'], $this->tenant->id);

    $channels = $event->broadcastOn();

    expect($channels)->toHaveCount(1);
    expect($channels[0]->name)->toBe("private-tenant.{$this->tenant->id}.notifications");
});

// ---------------------------------------------------------------------------
// Event dispatch verification
// ---------------------------------------------------------------------------

test('work order status update can dispatch WorkOrderStatusChanged broadcast', function () {
    Event::fake([WorkOrderStatusChanged::class]);

    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'created_by' => $this->user->id,
    ]);

    WorkOrderStatusChanged::dispatch($wo);

    Event::assertDispatched(WorkOrderStatusChanged::class);
});

test('service call status change can dispatch broadcast event', function () {
    Event::fake([ServiceCallStatusChanged::class]);

    $sc = ServiceCall::factory()->create(['tenant_id' => $this->tenant->id]);

    ServiceCallStatusChanged::dispatch($sc, 'open', 'in_progress', $this->user);

    Event::assertDispatched(ServiceCallStatusChanged::class, function ($event) use ($sc) {
        return $event->serviceCall->id === $sc->id && $event->toStatus === 'in_progress';
    });
});
