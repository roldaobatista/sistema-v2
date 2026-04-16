<?php

namespace App\Http\Requests\Os;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkOrderChecklistResponseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('os.work_order.update');
    }

    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);

        return [
            'responses' => 'required|array|min:1',
            'responses.*.checklist_item_id' => [
                'required',
                'integer',
                Rule::exists('service_checklist_items', 'id')->where(
                    fn ($q) => $q->whereIn('checklist_id', function ($sub) use ($tenantId) {
                        $sub->select('id')
                            ->from('service_checklists')
                            ->where('tenant_id', $tenantId);
                    })
                ),
            ],
            'responses.*.value' => 'nullable|string|max:5000',
            'responses.*.notes' => 'nullable|string|max:2000',
        ];
    }
}
