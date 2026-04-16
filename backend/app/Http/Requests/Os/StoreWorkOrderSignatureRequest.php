<?php

namespace App\Http\Requests\Os;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkOrderSignatureRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $routeWorkOrder = $this->route('work_order');

        $payload = [];

        if ($routeWorkOrder) {
            $payload['work_order_id'] = is_object($routeWorkOrder)
                ? $routeWorkOrder->id
                : (int) $routeWorkOrder;
            $payload['signer_type'] = $this->input('signer_type', 'customer');
        }

        if ($this->filled('signature') && ! $this->filled('signature_data')) {
            $payload['signature_data'] = $this->input('signature');
        }

        if ($this->filled('signature_data') && ! $this->filled('signature')) {
            $payload['signature'] = $this->input('signature_data');
        }

        if ($payload !== []) {
            $this->merge($payload);
        }
    }

    public function authorize(): bool
    {
        return $this->user()->can('os.work_order.update');
    }

    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);

        return [
            'work_order_id' => ['required', Rule::exists('work_orders', 'id')->where('tenant_id', $tenantId)],
            'signer_name' => 'required|string|max:255',
            'signer_document' => 'nullable|string|max:20',
            'signer_type' => 'required|string|in:customer,technician',
            'signature_data' => 'required|string',
        ];
    }
}
