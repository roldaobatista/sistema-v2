<?php

namespace App\Http\Requests\RemainingModules;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePhotoAnnotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('os.work_order.update');
    }

    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);

        return [
            'work_order_id' => ['required', Rule::exists('work_orders', 'id')->where('tenant_id', $tenantId)],
            'image_path' => 'required|string',
            'annotations' => 'required|array',
            'annotations.*.x' => 'required|numeric',
            'annotations.*.y' => 'required|numeric',
            'annotations.*.text' => 'required|string|max:200',
            'annotations.*.color' => 'nullable|string|max:7',
        ];
    }
}
