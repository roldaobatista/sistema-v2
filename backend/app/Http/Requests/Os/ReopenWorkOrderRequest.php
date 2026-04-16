<?php

namespace App\Http\Requests\Os;

use App\Models\AccountReceivable;
use App\Models\Invoice;
use App\Models\WorkOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ReopenWorkOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $workOrder = $this->route('work_order') ?? $this->route('workOrder');

        return $workOrder instanceof WorkOrder
            && $this->user()?->can('changeStatus', $workOrder);
    }

    public function rules(): array
    {
        return [];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $workOrder = $this->route('work_order') ?? $this->route('workOrder');

            if (! $workOrder instanceof WorkOrder) {
                return;
            }

            if ($workOrder->status !== WorkOrder::STATUS_CANCELLED) {
                $validator->errors()->add('status', 'Apenas OS canceladas podem ser reabertas.');
            }

            $hadInvoices = Invoice::where('work_order_id', $workOrder->id)->exists();
            if ($hadInvoices) {
                $validator->errors()->add('status', 'Não é possível reabrir esta OS — ela foi faturada anteriormente.');
            }

            $hasPaidReceivables = AccountReceivable::where('work_order_id', $workOrder->id)
                ->whereRaw('CAST(amount_paid AS DECIMAL(15,2)) > 0')
                ->exists();
            if ($hasPaidReceivables) {
                $validator->errors()->add('status', 'Não é possível reabrir esta OS — existem pagamentos realizados vinculados.');
            }
        });
    }
}
