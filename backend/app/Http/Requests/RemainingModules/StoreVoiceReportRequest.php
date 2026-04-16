<?php

namespace App\Http\Requests\RemainingModules;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVoiceReportRequest extends FormRequest
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
            'transcription' => 'required|string',
            'duration_seconds' => 'nullable|integer',
            'language' => 'nullable|in:pt_BR,en_US,es_ES',
        ];
    }
}
