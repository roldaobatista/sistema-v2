<?php

namespace App\Http\Requests\TechSync;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TechSyncUploadPhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('os.work_order.update');
    }

    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);

        return [
            'file' => 'required|file|image|mimes:jpg,jpeg,png,webp|max:5120',
            'work_order_id' => ['required', 'integer', Rule::exists('work_orders', 'id')->where('tenant_id', $tenantId)],
            'entity_type' => 'required|string|in:before,after,checklist,expense,general',
            'entity_id' => 'nullable|string',
        ];
    }
}
