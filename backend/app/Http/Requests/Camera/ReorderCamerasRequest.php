<?php

namespace App\Http\Requests\Camera;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReorderCamerasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('tv.camera.manage');
    }

    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);

        return [
            'order' => 'required|array',
            'order.*' => ['integer', Rule::exists('cameras', 'id')->where('tenant_id', $tenantId)],
        ];
    }
}
