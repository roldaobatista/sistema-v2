<?php

namespace App\Http\Requests\Os;

use App\Models\WorkOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AuthorizeDispatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('os.work_order.authorize_dispatch') ?? false;
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

            $allowedStatuses = [WorkOrder::STATUS_OPEN, WorkOrder::STATUS_AWAITING_DISPATCH];
            if (! in_array($workOrder->status, $allowedStatuses, true)) {
                $validator->errors()->add('status', 'Autorização de deslocamento só é permitida para OS abertas ou aguardando despacho.');
            }

            if ($workOrder->dispatch_authorized_at) {
                $validator->errors()->add('status', 'Deslocamento já autorizado em '.$workOrder->dispatch_authorized_at->format('d/m/Y H:i'));
            }
        });
    }
}
