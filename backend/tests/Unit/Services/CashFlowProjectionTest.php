<?php

use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CashFlowProjectionService;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app()->instance('current_tenant_id', $this->tenant->id);

    $this->user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'current_tenant_id' => $this->tenant->id,
    ]);

    $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->service = app(CashFlowProjectionService::class);
});

test('returns correct period in projection', function () {
    $from = Carbon::parse('2026-01-01');
    $to = Carbon::parse('2026-01-31');

    $result = $this->service->project($from, $to, $this->tenant->id);

    expect($result['period']['from'])->toBe('2026-01-01');
    expect($result['period']['to'])->toBe('2026-01-31');
});

test('projects pending receivables as entradas_previstas', function () {
    $from = Carbon::parse('2026-01-01');
    $to = Carbon::parse('2026-01-31');

    AccountReceivable::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'created_by' => $this->user->id,
        'amount' => 5000.00,
        'amount_paid' => 0,
        'due_date' => '2026-01-15',
        'status' => AccountReceivable::STATUS_PENDING,
    ]);

    AccountReceivable::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'created_by' => $this->user->id,
        'amount' => 3000.00,
        'amount_paid' => 1000.00,
        'due_date' => '2026-01-20',
        'status' => AccountReceivable::STATUS_PARTIAL,
    ]);

    $result = $this->service->project($from, $to, $this->tenant->id);

    // 5000 + (3000-1000) = 7000
    expect(floatval($result['summary']['entradas_previstas']))->toBe(7000.00);
});

test('excludes cancelled and renegotiated payables from saidas_previstas', function () {
    $from = Carbon::parse('2026-01-01');
    $to = Carbon::parse('2026-01-31');

    AccountPayable::factory()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $this->user->id,
        'amount' => 2000.00,
        'amount_paid' => 0,
        'due_date' => '2026-01-10',
        'status' => AccountPayable::STATUS_PENDING,
    ]);

    AccountPayable::factory()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $this->user->id,
        'amount' => 3000.00,
        'amount_paid' => 0,
        'due_date' => '2026-01-15',
        'status' => 'cancelled',
    ]);

    $result = $this->service->project($from, $to, $this->tenant->id);

    // Only the pending one counts
    expect(floatval($result['summary']['saidas_previstas']))->toBe(2000.00);
});

test('calculates saldo_previsto as entradas minus saidas', function () {
    $from = Carbon::parse('2026-02-01');
    $to = Carbon::parse('2026-02-28');

    AccountReceivable::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'created_by' => $this->user->id,
        'amount' => 10000.00,
        'amount_paid' => 0,
        'due_date' => '2026-02-15',
        'status' => AccountReceivable::STATUS_PENDING,
    ]);

    AccountPayable::factory()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $this->user->id,
        'amount' => 4000.00,
        'amount_paid' => 0,
        'due_date' => '2026-02-10',
        'status' => AccountPayable::STATUS_PENDING,
    ]);

    $result = $this->service->project($from, $to, $this->tenant->id);

    $saldo = floatval($result['summary']['saldo_previsto']);
    // Total entradas = 10000, total saidas = 4000 => saldo = 6000
    expect($saldo)->toBe(6000.00);
});

test('returns empty summary when no financial data exists', function () {
    $from = Carbon::parse('2026-06-01');
    $to = Carbon::parse('2026-06-30');

    $result = $this->service->project($from, $to, $this->tenant->id);

    expect(floatval($result['summary']['entradas_previstas']))->toBe(0.00);
    expect(floatval($result['summary']['saidas_previstas']))->toBe(0.00);
    expect(floatval($result['summary']['saldo_previsto']))->toBe(0.00);
});

test('generates weekly breakdown', function () {
    $from = Carbon::parse('2026-03-02'); // Monday
    $to = Carbon::parse('2026-03-29'); // Sunday, 4 full weeks

    AccountReceivable::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'created_by' => $this->user->id,
        'amount' => 1000.00,
        'amount_paid' => 0,
        'due_date' => '2026-03-05',
        'status' => AccountReceivable::STATUS_PENDING,
    ]);

    $result = $this->service->project($from, $to, $this->tenant->id);

    expect($result['by_week'])->toBeArray();
    expect(count($result['by_week']))->toBeGreaterThanOrEqual(1);
    expect($result['by_week'][0])->toHaveKeys(['week', 'from', 'to', 'entradas_previstas', 'saidas_previstas']);
});

test('excludes paid payables from saidas_previstas', function () {
    $from = Carbon::parse('2026-01-01');
    $to = Carbon::parse('2026-01-31');

    AccountPayable::factory()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $this->user->id,
        'amount' => 5000.00,
        'amount_paid' => 5000.00,
        'due_date' => '2026-01-10',
        'status' => 'paid',
    ]);

    $result = $this->service->project($from, $to, $this->tenant->id);

    expect(floatval($result['summary']['saidas_previstas']))->toBe(0.00);
});

test('overdue receivables are included in entradas_previstas', function () {
    $from = Carbon::parse('2026-01-01');
    $to = Carbon::parse('2026-01-31');

    AccountReceivable::factory()->overdue()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'created_by' => $this->user->id,
        'amount' => 2000.00,
        'amount_paid' => 0,
        'due_date' => '2026-01-05',
    ]);

    $result = $this->service->project($from, $to, $this->tenant->id);

    expect(floatval($result['summary']['entradas_previstas']))->toBe(2000.00);
});

test('90 day projection spans 3 months', function () {
    $from = Carbon::now();
    $to = Carbon::now()->addDays(90);

    $result = $this->service->project($from, $to, $this->tenant->id);

    $periodFrom = Carbon::parse($result['period']['from']);
    $periodTo = Carbon::parse($result['period']['to']);

    expect((int) $periodFrom->diffInDays($periodTo))->toBe(90);
});

test('partial payments reduce entradas_previstas correctly', function () {
    $from = Carbon::parse('2026-04-01');
    $to = Carbon::parse('2026-04-30');

    AccountReceivable::factory()->partial()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'created_by' => $this->user->id,
        'amount' => 4000.00,
        'amount_paid' => 1500.00,
        'due_date' => '2026-04-15',
    ]);

    $result = $this->service->project($from, $to, $this->tenant->id);

    // 4000 - 1500 = 2500 remaining
    expect(floatval($result['summary']['entradas_previstas']))->toBe(2500.00);
});

test('projection correctly handles receivables outside the period', function () {
    $from = Carbon::parse('2026-03-01');
    $to = Carbon::parse('2026-03-31');

    // This receivable is outside the period
    AccountReceivable::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'created_by' => $this->user->id,
        'amount' => 9999.00,
        'amount_paid' => 0,
        'due_date' => '2026-04-15',
        'status' => AccountReceivable::STATUS_PENDING,
    ]);

    $result = $this->service->project($from, $to, $this->tenant->id);

    expect(floatval($result['summary']['entradas_previstas']))->toBe(0.00);
});
