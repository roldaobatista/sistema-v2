<?php

use App\Enums\DebtRenegotiationStatus;
use App\Enums\FinancialStatus;
use App\Models\AccountReceivable;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Services\DebtRenegotiationService;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app()->instance('current_tenant_id', $this->tenant->id);

    $this->user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'current_tenant_id' => $this->tenant->id,
    ]);

    $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->service = app(DebtRenegotiationService::class);
});

test('creates renegotiation with correct original total', function () {
    $ar1 = AccountReceivable::factory()->overdue()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'created_by' => $this->user->id,
        'amount' => 3000.00,
    ]);

    $ar2 = AccountReceivable::factory()->overdue()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'created_by' => $this->user->id,
        'amount' => 2000.00,
    ]);

    $renegotiation = $this->service->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'negotiated_total' => 4500.00,
        'discount_amount' => 500.00,
        'interest_amount' => 0,
        'fine_amount' => 0,
        'new_installments' => 3,
        'first_due_date' => now()->addDays(30),
    ], [$ar1->id, $ar2->id], $this->user->id);

    expect((float) $renegotiation->original_total)->toBe(5000.00);
    expect((float) $renegotiation->negotiated_total)->toBe(4500.00);
    expect($renegotiation->status)->toBe(DebtRenegotiationStatus::PENDING);
    expect($renegotiation->items)->toHaveCount(2);
});

test('creates renegotiation items linking original receivables', function () {
    $ar1 = AccountReceivable::factory()->overdue()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'created_by' => $this->user->id,
        'amount' => 1000.00,
    ]);

    $renegotiation = $this->service->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'negotiated_total' => 900.00,
        'discount_amount' => 100.00,
        'new_installments' => 1,
        'first_due_date' => now()->addDays(30),
    ], [$ar1->id], $this->user->id);

    expect($renegotiation->items->first()->account_receivable_id)->toBe($ar1->id);
    expect((float) $renegotiation->items->first()->original_amount)->toBe(1000.00);
});

test('approve marks original receivables as renegotiated', function () {
    $ar1 = AccountReceivable::factory()->overdue()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'created_by' => $this->user->id,
        'amount' => 2000.00,
    ]);

    $renegotiation = $this->service->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'negotiated_total' => 1800.00,
        'discount_amount' => 200.00,
        'new_installments' => 2,
        'first_due_date' => now()->addDays(30),
    ], [$ar1->id], $this->user->id);

    $this->service->approve($renegotiation, $this->user->id);

    expect($ar1->fresh()->status->value ?? $ar1->fresh()->status)->toBe(FinancialStatus::RENEGOTIATED->value);
});

test('approve creates new installments with correct amounts', function () {
    $ar1 = AccountReceivable::factory()->overdue()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'created_by' => $this->user->id,
        'amount' => 6000.00,
    ]);

    $renegotiation = $this->service->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'negotiated_total' => 6000.00,
        'discount_amount' => 0,
        'new_installments' => 3,
        'first_due_date' => now()->addDays(30),
    ], [$ar1->id], $this->user->id);

    $this->service->approve($renegotiation, $this->user->id);

    // New receivables should be created (excluding original renegotiated ones)
    $newReceivables = AccountReceivable::where('tenant_id', $this->tenant->id)
        ->where('status', FinancialStatus::PENDING->value)
        ->where('notes', 'LIKE', "%renegotiation:{$renegotiation->id}%")
        ->get();

    expect($newReceivables)->toHaveCount(3);

    $total = $newReceivables->sum('amount');
    expect(round($total, 2))->toBe(6000.00);
});

test('approve puts remainder on last installment (BR financial standard)', function () {
    $ar1 = AccountReceivable::factory()->overdue()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'created_by' => $this->user->id,
        'amount' => 100.00,
    ]);

    $renegotiation = $this->service->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'negotiated_total' => 100.00,
        'discount_amount' => 0,
        'new_installments' => 3,
        'first_due_date' => now()->addDays(30),
    ], [$ar1->id], $this->user->id);

    $this->service->approve($renegotiation, $this->user->id);

    $newReceivables = AccountReceivable::where('tenant_id', $this->tenant->id)
        ->where('status', FinancialStatus::PENDING->value)
        ->where('notes', 'LIKE', "%renegotiation:{$renegotiation->id}%")
        ->orderBy('due_date')
        ->get();

    // 100/3 = 33.33, remainder 0.01 on LAST installment (padrão BR)
    expect((float) $newReceivables[0]->amount)->toBe(33.33);
    expect((float) $newReceivables[1]->amount)->toBe(33.33);
    expect((float) $newReceivables[2]->amount)->toBe(33.34);
});

test('approve sets status to approved with timestamp', function () {
    $ar1 = AccountReceivable::factory()->overdue()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'created_by' => $this->user->id,
        'amount' => 1000.00,
    ]);

    $renegotiation = $this->service->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'negotiated_total' => 900.00,
        'discount_amount' => 100.00,
        'new_installments' => 1,
        'first_due_date' => now()->addDays(30),
    ], [$ar1->id], $this->user->id);

    $result = $this->service->approve($renegotiation, $this->user->id);

    expect($result->status)->toBe(DebtRenegotiationStatus::APPROVED);
    expect($result->approved_by)->toBe($this->user->id);
    expect($result->approved_at)->not->toBeNull();
});

test('new installments have incrementing monthly due dates', function () {
    $ar1 = AccountReceivable::factory()->overdue()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'created_by' => $this->user->id,
        'amount' => 3000.00,
    ]);

    $firstDueDate = now()->addDays(30);

    $renegotiation = $this->service->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'negotiated_total' => 3000.00,
        'discount_amount' => 0,
        'new_installments' => 3,
        'first_due_date' => $firstDueDate,
    ], [$ar1->id], $this->user->id);

    $this->service->approve($renegotiation, $this->user->id);

    $newReceivables = AccountReceivable::where('tenant_id', $this->tenant->id)
        ->where('notes', 'LIKE', "%renegotiation:{$renegotiation->id}%")
        ->orderBy('due_date')
        ->get();

    $months = $newReceivables->map(fn ($r) => $r->due_date->format('Y-m'));

    expect($months->unique())->toHaveCount(3);
});

test('reject sets status to rejected', function () {
    $ar1 = AccountReceivable::factory()->overdue()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'created_by' => $this->user->id,
        'amount' => 1000.00,
    ]);

    $renegotiation = $this->service->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'negotiated_total' => 800.00,
        'discount_amount' => 200.00,
        'new_installments' => 1,
        'first_due_date' => now()->addDays(30),
    ], [$ar1->id], $this->user->id);

    $result = $this->service->reject($renegotiation);

    expect($result->status)->toBe(DebtRenegotiationStatus::REJECTED);
});

test('merge multiple receivables calculates correct original total', function () {
    $receivables = [];
    for ($i = 0; $i < 5; $i++) {
        $receivables[] = AccountReceivable::factory()->overdue()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'amount' => 1000.00 + ($i * 500),
        ]);
    }

    $ids = collect($receivables)->pluck('id')->toArray();
    $expectedTotal = collect($receivables)->sum('amount');

    $renegotiation = $this->service->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'negotiated_total' => $expectedTotal - 500,
        'discount_amount' => 500.00,
        'new_installments' => 6,
        'first_due_date' => now()->addDays(30),
    ], $ids, $this->user->id);

    expect((float) $renegotiation->original_total)->toBe((float) $expectedTotal);
    expect($renegotiation->items)->toHaveCount(5);
});

test('approve blocks when negotiated_total does not match accounting formula', function () {
    $ar1 = AccountReceivable::factory()->overdue()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'created_by' => $this->user->id,
        'amount' => 5000.00,
    ]);

    $renegotiation = $this->service->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'negotiated_total' => 9999.00, // Valor inconsistente
        'discount_amount' => 500.00,
        'interest_amount' => 100.00,
        'fine_amount' => 50.00,
        'new_installments' => 3,
        'first_due_date' => now()->addDays(30),
    ], [$ar1->id], $this->user->id);

    expect(fn () => $this->service->approve($renegotiation, $this->user->id))
        ->toThrow(HttpException::class, 'inconsistente');
});

test('approve blocks double approval (TOCTOU protection)', function () {
    $ar1 = AccountReceivable::factory()->overdue()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'created_by' => $this->user->id,
        'amount' => 1000.00,
    ]);

    $renegotiation = $this->service->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'negotiated_total' => 1000.00,
        'discount_amount' => 0,
        'interest_amount' => 0,
        'fine_amount' => 0,
        'new_installments' => 1,
        'first_due_date' => now()->addDays(30),
    ], [$ar1->id], $this->user->id);

    // Primeira aprovação = OK
    $this->service->approve($renegotiation, $this->user->id);

    // Segunda = bloqueada
    expect(fn () => $this->service->approve($renegotiation, $this->user->id))
        ->toThrow(HttpException::class, 'já processada');
});

test('approve blocks when original receivable is already paid', function () {
    $ar1 = AccountReceivable::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'created_by' => $this->user->id,
        'amount' => 1000.00,
        'amount_paid' => 1000.00,
        'status' => FinancialStatus::PAID->value,
    ]);

    $renegotiation = $this->service->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'negotiated_total' => 1000.00,
        'discount_amount' => 0,
        'interest_amount' => 0,
        'fine_amount' => 0,
        'new_installments' => 1,
        'first_due_date' => now()->addDays(30),
    ], [$ar1->id], $this->user->id);

    expect(fn () => $this->service->approve($renegotiation, $this->user->id))
        ->toThrow(HttpException::class, 'não pode ser renegociada');
});
