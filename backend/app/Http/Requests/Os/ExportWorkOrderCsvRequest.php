<?php

namespace App\Http\Requests\Os;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExportWorkOrderCsvRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('os.work_order.export');
    }

    public function rules(): array
    {
        return [
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'status' => ['nullable', 'string', 'max:50'],
            'priority' => ['nullable', 'string', 'max:50'],
            'assigned_to' => ['nullable', 'integer', Rule::exists('users', 'id')->where('current_tenant_id', (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id))],
            'format' => ['nullable', 'string', 'in:csv,xlsx'],
        ];
    }
}
