<?php

declare(strict_types=1);

use App\Models\AccountReceivable;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Re-auditoria Camada 1 2026-04-19 — finding data-03.
 *
 * Cenário: `payments` é o registro de baixa financeira de AR/AP e JAMAIS
 * deveria ser deletado fisicamente. A tabela estava sem `softDeletes()`, o
 * que permitia deleções físicas (direta ou via cascade de `AccountReceivable`)
 * sem rastro de auditoria.
 *
 * Regressão: garantir que `payments.deleted_at` existe, `Payment` usa trait
 * `SoftDeletes` e a semântica soft-delete/restore/withTrashed funciona.
 */
beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'current_tenant_id' => $this->tenant->id,
    ]);

    app()->instance('current_tenant_id', $this->tenant->id);

    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->receivable = AccountReceivable::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'created_by' => $this->user->id,
        'amount' => 100.00,
    ]);
});

describe('Payment soft-delete (finding data-03)', function (): void {
    it('has deleted_at column on payments table', function (): void {
        expect(Schema::hasColumn('payments', 'deleted_at'))->toBeTrue();
    });

    it('uses SoftDeletes trait on Payment model', function (): void {
        $traits = class_uses_recursive(Payment::class);
        expect($traits)->toHaveKey(SoftDeletes::class);
    });

    it('soft-deletes a payment instead of removing it physically', function (): void {
        $payment = Payment::create([
            'tenant_id' => $this->tenant->id,
            'payable_type' => AccountReceivable::class,
            'payable_id' => $this->receivable->id,
            'received_by' => $this->user->id,
            'amount' => 50.00,
            'payment_method' => 'pix',
            'payment_date' => now()->toDateString(),
        ]);

        $paymentId = $payment->id;

        $payment->delete();

        // default query NÃO retorna soft-deleted
        expect(Payment::query()->find($paymentId))->toBeNull();

        // linha continua existindo no banco com deleted_at setado
        $raw = DB::table('payments')->where('id', $paymentId)->first();
        expect($raw)->not->toBeNull()
            ->and($raw->deleted_at)->not->toBeNull();
    });

    it('restores a soft-deleted payment', function (): void {
        $payment = Payment::create([
            'tenant_id' => $this->tenant->id,
            'payable_type' => AccountReceivable::class,
            'payable_id' => $this->receivable->id,
            'received_by' => $this->user->id,
            'amount' => 30.00,
            'payment_method' => 'dinheiro',
            'payment_date' => now()->toDateString(),
        ]);

        $payment->delete();
        expect(Payment::query()->find($payment->id))->toBeNull();

        Payment::withTrashed()->find($payment->id)->restore();

        $restored = Payment::query()->find($payment->id);
        expect($restored)->not->toBeNull()
            ->and($restored->deleted_at)->toBeNull();
    });

    it('returns soft-deleted payments when using withTrashed()', function (): void {
        $payment = Payment::create([
            'tenant_id' => $this->tenant->id,
            'payable_type' => AccountReceivable::class,
            'payable_id' => $this->receivable->id,
            'received_by' => $this->user->id,
            'amount' => 20.00,
            'payment_method' => 'boleto',
            'payment_date' => now()->toDateString(),
        ]);

        $payment->delete();

        expect(Payment::query()->find($payment->id))->toBeNull();
        expect(Payment::withTrashed()->find($payment->id))->not->toBeNull();
    });
});
