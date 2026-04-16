<?php

namespace App\Http\Requests\Os;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexWorkOrderTimeLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('os.work_order.view');
    }

    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);

        return [
            'work_order_id' => ['required', Rule::exists('work_orders', 'id')->where('tenant_id', $tenantId)],
        ];
    }
}
