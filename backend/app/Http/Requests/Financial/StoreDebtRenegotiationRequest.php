<?php

namespace App\Http\Requests\Financial;

use App\Enums\FinancialStatus;
use App\Models\AccountReceivable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreDebtRenegotiationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('finance.receivable.create');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'description' => $this->description === '' ? null : $this->description,
            'discount_percentage' => $this->discount_percentage === '' ? null : $this->discount_percentage,
            'interest_rate' => $this->interest_rate === '' ? null : $this->interest_rate,
            'notes' => $this->notes === '' ? null : $this->notes,
        ]);
    }

    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id ?? 0);

        return [
            'customer_id' => [
                'required',
                Rule::exists('customers', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'receivable_ids' => 'required|array|min:1',
            'receivable_ids.*' => ['integer', Rule::exists('accounts_receivable', 'id')->where('tenant_id', $tenantId)],
            'description' => 'nullable|string',
            'new_due_date' => 'required|date|after:today',
            'installments' => 'required|integer|min:1|max:48',
            'discount_percentage' => 'nullable|numeric|min:0|max:100',
            'interest_rate' => 'nullable|numeric|min:0',
            'fine_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id ?? 0);
            $receivableIds = array_values(array_unique(array_map('intval', (array) $this->input('receivable_ids', []))));

            if ($tenantId <= 0 || $receivableIds === []) {
                return;
            }

            $receivables = AccountReceivable::query()
                ->where('tenant_id', $tenantId)
                ->whereIn('id', $receivableIds)
                ->get(['id', 'customer_id', 'work_order_id', 'amount', 'amount_paid', 'status']);

            if ($receivables->count() !== count($receivableIds)) {
                return;
            }

            $customerIds = $receivables->pluck('customer_id')->unique()->filter()->values();
            if ($customerIds->count() !== 1 || (int) $customerIds->first() !== (int) $this->input('customer_id')) {
                $validator->errors()->add('receivable_ids', 'Todos os titulos devem pertencer ao cliente informado.');
            }

            $invalidStatuses = $receivables
                ->filter(function (AccountReceivable $receivable) {
                    $status = $receivable->status instanceof FinancialStatus
                        ? $receivable->status
                        : FinancialStatus::tryFrom((string) $receivable->status);

                    return ! in_array($status, [
                        FinancialStatus::PENDING,
                        FinancialStatus::PARTIAL,
                        FinancialStatus::OVERDUE,
                    ], true);
                });

            if ($invalidStatuses->isNotEmpty()) {
                $validator->errors()->add('receivable_ids', 'A renegociacao aceita apenas titulos em aberto.');
            }

            $receivablesWithNoBalance = $receivables->filter(function (AccountReceivable $receivable) {
                return bccomp(
                    bcsub((string) $receivable->amount, (string) $receivable->amount_paid, 2),
                    '0',
                    2
                ) <= 0;
            });

            if ($receivablesWithNoBalance->isNotEmpty()) {
                $validator->errors()->add('receivable_ids', 'Todos os titulos devem ter saldo em aberto para renegociacao.');
            }

            $distinctWorkOrders = $receivables
                ->pluck('work_order_id')
                ->filter(fn ($value) => $value !== null)
                ->unique()
                ->values();

            if ($distinctWorkOrders->count() > 1) {
                $validator->errors()->add('receivable_ids', 'Nao e permitido misturar titulos de OS diferentes na mesma renegociacao.');
            }
        });
    }
}
