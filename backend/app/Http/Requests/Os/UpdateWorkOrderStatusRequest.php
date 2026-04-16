<?php

namespace App\Http\Requests\Os;

use App\Models\WorkOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWorkOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('os.work_order.change_status');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'notes' => $this->notes === '' ? null : $this->notes,
            'agreed_payment_method' => $this->agreed_payment_method === '' ? null : $this->agreed_payment_method,
            'agreed_payment_notes' => $this->agreed_payment_notes === '' ? null : $this->agreed_payment_notes,
        ]);
    }

    public function rules(): array
    {
        return [
            'status' => 'required|in:'.implode(',', array_keys(WorkOrder::STATUSES)),
            'notes' => 'nullable|string|max:2000',
            'agreed_payment_method' => ['nullable', 'string', 'max:50', Rule::in(array_keys(WorkOrder::AGREED_PAYMENT_METHODS))],
            'agreed_payment_notes' => 'nullable|string|max:500',
        ];
    }
}
