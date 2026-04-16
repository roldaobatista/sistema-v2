<?php

namespace App\Services;

use App\Enums\DebtRenegotiationStatus;
use App\Enums\FinancialStatus;
use App\Models\AccountReceivable;
use App\Models\DebtRenegotiation;
use App\Models\DebtRenegotiationItem;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;

class DebtRenegotiationService
{
    /**
     * Cria uma renegociação de dívida a partir de parcelas em atraso.
     */
    public function create(array $data, array $receivableIds, int $userId): DebtRenegotiation
    {
        return DB::transaction(function () use ($data, $receivableIds, $userId) {
            $receivables = AccountReceivable::where('tenant_id', $data['tenant_id'])
                ->whereIn('id', $receivableIds)
                ->get();
            $originalTotal = $receivables->reduce(fn (string $carry, $r) => bcadd($carry, (string) $r->amount, 2), '0');

            $renegotiation = DebtRenegotiation::create([
                'tenant_id' => $data['tenant_id'],
                'customer_id' => $data['customer_id'],
                'original_total' => $originalTotal,
                'negotiated_total' => $data['negotiated_total'],
                'discount_amount' => (string) ($data['discount_amount'] ?? 0),
                'interest_amount' => (string) ($data['interest_amount'] ?? 0),
                'fine_amount' => (string) ($data['fine_amount'] ?? 0),
                'new_installments' => $data['new_installments'],
                'first_due_date' => $data['first_due_date'],
                'notes' => $data['notes'] ?? null,
                'status' => 'pending',
                'created_by' => $userId,
            ]);

            foreach ($receivables as $ar) {
                DebtRenegotiationItem::create([
                    'debt_renegotiation_id' => $renegotiation->id,
                    'account_receivable_id' => $ar->id,
                    'original_amount' => $ar->amount,
                ]);
            }

            return $renegotiation->load('items');
        });
    }

    /**
     * Aprova e executa a renegociação: cancela parcelas antigas, cria novas.
     */
    public function approve(DebtRenegotiation $renegotiation, int $approverId): DebtRenegotiation
    {
        return DB::transaction(function () use ($renegotiation, $approverId) {
            // Lock renegociação para evitar aprovação dupla (TOCTOU)
            /** @var DebtRenegotiation $renegotiation */
            $renegotiation = DebtRenegotiation::query()->lockForUpdate()->findOrFail($renegotiation->id);
            /** @var mixed $currentStatusRaw */
            $currentStatusRaw = $renegotiation->getAttribute('status');
            $currentStatus = $currentStatusRaw instanceof DebtRenegotiationStatus
                ? $currentStatusRaw
                : DebtRenegotiationStatus::tryFrom((string) $currentStatusRaw);

            if ($currentStatus !== DebtRenegotiationStatus::PENDING) {
                abort(422, 'Renegociação já processada ou não encontrada.');
            }

            // Validação contábil: negotiated_total = original - discount + interest + fine
            $expectedTotal = bcadd(
                bcsub(Decimal::string($renegotiation->original_total), Decimal::string($renegotiation->discount_amount), 2),
                bcadd(Decimal::string($renegotiation->interest_amount), Decimal::string($renegotiation->fine_amount), 2),
                2
            );
            if (bccomp($expectedTotal, Decimal::string($renegotiation->negotiated_total), 2) !== 0) {
                abort(422, 'Valor negociado inconsistente (esperado R$ '.$expectedTotal.', recebido R$ '.$renegotiation->negotiated_total.').');
            }

            // Lock parcelas originais antes de renegociar — previne pagamento concorrente
            $renegotiation->loadMissing('items');
            $itemIds = $renegotiation->items->pluck('account_receivable_id')->filter()->all();
            $receivables = AccountReceivable::where('tenant_id', $renegotiation->tenant_id)
                ->whereIn('id', $itemIds)->lockForUpdate()->get();

            foreach ($receivables as $ar) {
                /** @var mixed $statusRaw */
                $statusRaw = $ar->getAttribute('status');
                $statusValue = $statusRaw instanceof FinancialStatus
                    ? $statusRaw
                    : FinancialStatus::tryFrom((string) $statusRaw);
                if (! $statusValue || ! $statusValue->isOpen()) {
                    $statusLabel = $statusValue instanceof FinancialStatus ? $statusValue->value : (string) $statusRaw;
                    abort(422, "Parcela #{$ar->id} não pode ser renegociada (status: {$statusLabel}).");
                }
                $ar->update(['status' => FinancialStatus::RENEGOTIATED->value]);
            }

            // Cria novas parcelas (bcmath para precisão financeira)
            $negotiatedTotal = Decimal::string($renegotiation->negotiated_total);
            $installments = Decimal::string($renegotiation->new_installments, 0);
            $installmentAmount = bcdiv($negotiatedTotal, $installments, 2);
            $remainder = bcsub($negotiatedTotal, bcmul($installmentAmount, $installments, 2), 2);

            $n = (int) $renegotiation->new_installments;
            for ($i = 0; $i < $n; $i++) {
                // Centavos na última parcela (padrão financeiro BR)
                $amount = ($i === $n - 1)
                    ? bcadd($installmentAmount, $remainder, 2)
                    : $installmentAmount;

                AccountReceivable::create([
                    'tenant_id' => $renegotiation->tenant_id,
                    'customer_id' => $renegotiation->customer_id,
                    'created_by' => $approverId,
                    'description' => "Renegociação #{$renegotiation->id} — Parcela ".($i + 1)."/{$n}",
                    'amount' => $amount,
                    'amount_paid' => 0,
                    'due_date' => $renegotiation->first_due_date->copy()->addMonths($i),
                    'status' => FinancialStatus::PENDING->value,
                    'notes' => "renegotiation:{$renegotiation->id}",
                ]);
            }

            $renegotiation->update([
                'status' => DebtRenegotiationStatus::APPROVED->value,
                'approved_by' => $approverId,
                'approved_at' => now(),
            ]);

            return $renegotiation->fresh();
        });
    }

    /**
     * Rejeita a renegociação.
     */
    public function reject(DebtRenegotiation $renegotiation): DebtRenegotiation
    {
        $renegotiation->update(['status' => DebtRenegotiationStatus::REJECTED->value]);

        return $renegotiation;
    }
}
