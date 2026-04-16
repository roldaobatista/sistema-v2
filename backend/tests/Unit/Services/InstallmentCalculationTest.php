<?php

use App\Models\AccountReceivable;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderItem;
use App\Services\InvoicingService;
use Illuminate\Database\Eloquent\Model;

beforeEach(function () {
    Model::unguard();
    $this->tenant = Tenant::factory()->create();
    app()->instance('current_tenant_id', $this->tenant->id);

    $this->user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'current_tenant_id' => $this->tenant->id,
    ]);

    $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->service = app(InvoicingService::class);
});

/** Helper to create a completed WO with a single service item */
function createWoWithTotal(float $total, $context): WorkOrder
{
    $wo = WorkOrder::factory()->completed()->create([
        'tenant_id' => $context->tenant->id,
        'customer_id' => $context->customer->id,
        'created_by' => $context->user->id,
        'total' => $total,
    ]);

    WorkOrderItem::create([
        'work_order_id' => $wo->id,
        'tenant_id' => $context->tenant->id,
        'description' => 'Serviço de calibração',
        'quantity' => 1,
        'unit_price' => $total,
        'total' => $total,
        'type' => 'service',
    ]);

    return $wo;
}

test('single installment equals full amount', function () {
    $wo = createWoWithTotal(5000.00, $this);

    $result = $this->service->generateFromWorkOrder($wo, $this->user->id, 1);

    expect($result['receivables'])->toHaveCount(1);
    expect((float) $result['receivables'][0]->amount)->toBe(5000.00);
});

test('two equal installments sum to total', function () {
    $wo = createWoWithTotal(10000.00, $this);

    $result = $this->service->generateFromWorkOrder($wo, $this->user->id, 2);

    expect($result['receivables'])->toHaveCount(2);
    $total = collect($result['receivables'])->sum(fn ($r) => (float) $r->amount);
    expect($total)->toBe(10000.00);
    expect((float) $result['receivables'][0]->amount)->toBe(5000.00);
    expect((float) $result['receivables'][1]->amount)->toBe(5000.00);
});

test('three installments with remainder on first', function () {
    $wo = createWoWithTotal(100.00, $this);

    $result = $this->service->generateFromWorkOrder($wo, $this->user->id, 3);

    expect($result['receivables'])->toHaveCount(3);

    $amounts = collect($result['receivables'])->map(fn ($r) => (float) $r->amount);
    $total = $amounts->sum();

    expect(round($total, 2))->toBe(100.00);

    // First installment gets the remainder: 33.33 + 0.01 = 33.34
    expect($amounts[0])->toBe(33.34);
    expect($amounts[1])->toBe(33.33);
    expect($amounts[2])->toBe(33.33);
});

test('installments have incrementing due dates by month', function () {
    $wo = createWoWithTotal(6000.00, $this);

    $result = $this->service->generateFromWorkOrder($wo, $this->user->id, 3);

    $dueDates = collect($result['receivables'])->map(fn ($r) => $r->due_date->format('Y-m'));
    $uniqueMonths = $dueDates->unique();

    expect($uniqueMonths)->toHaveCount(3);
});

test('installment descriptions include numbering', function () {
    $wo = createWoWithTotal(4000.00, $this);

    $result = $this->service->generateFromWorkOrder($wo, $this->user->id, 4);

    expect($result['receivables'][0]->description)->toContain('Parcela 1/4');
    expect($result['receivables'][1]->description)->toContain('Parcela 2/4');
    expect($result['receivables'][2]->description)->toContain('Parcela 3/4');
    expect($result['receivables'][3]->description)->toContain('Parcela 4/4');
});

test('all installments are created with pending status', function () {
    $wo = createWoWithTotal(3000.00, $this);

    $result = $this->service->generateFromWorkOrder($wo, $this->user->id, 3);

    foreach ($result['receivables'] as $ar) {
        expect($ar->status->value ?? $ar->status)->toBe(AccountReceivable::STATUS_PENDING);
        expect((float) $ar->amount_paid)->toBe(0.0);
    }
});

test('all installments reference the same invoice', function () {
    $wo = createWoWithTotal(6000.00, $this);

    $result = $this->service->generateFromWorkOrder($wo, $this->user->id, 3);

    $invoiceId = $result['invoice']->id;
    foreach ($result['receivables'] as $ar) {
        expect($ar->invoice_id)->toBe($invoiceId);
    }
});

test('installments with exact division have no remainder', function () {
    $wo = createWoWithTotal(9000.00, $this);

    $result = $this->service->generateFromWorkOrder($wo, $this->user->id, 3);

    $amounts = collect($result['receivables'])->map(fn ($r) => (float) $r->amount);

    // 9000 / 3 = 3000 exactly
    expect($amounts[0])->toBe(3000.00);
    expect($amounts[1])->toBe(3000.00);
    expect($amounts[2])->toBe(3000.00);
});

test('large number of installments handles precision correctly', function () {
    $wo = createWoWithTotal(10000.00, $this);

    $result = $this->service->generateFromWorkOrder($wo, $this->user->id, 7);

    expect($result['receivables'])->toHaveCount(7);

    $total = collect($result['receivables'])->sum(fn ($r) => (float) $r->amount);
    expect(round($total, 2))->toBe(10000.00);
});

test('single installment does not include numbering in description', function () {
    $wo = createWoWithTotal(2000.00, $this);

    $result = $this->service->generateFromWorkOrder($wo, $this->user->id, 1);

    expect($result['receivables'][0]->description)->not->toContain('Parcela');
});

test('all installments belong to correct tenant and customer', function () {
    $wo = createWoWithTotal(6000.00, $this);

    $result = $this->service->generateFromWorkOrder($wo, $this->user->id, 3);

    foreach ($result['receivables'] as $ar) {
        expect($ar->tenant_id)->toBe($this->tenant->id);
        expect($ar->customer_id)->toBe($this->customer->id);
    }
});

test('very small total splits correctly into installments', function () {
    $wo = createWoWithTotal(1.00, $this);

    $result = $this->service->generateFromWorkOrder($wo, $this->user->id, 3);

    $total = collect($result['receivables'])->sum(fn ($r) => (float) $r->amount);
    expect(round($total, 2))->toBe(1.00);
});
