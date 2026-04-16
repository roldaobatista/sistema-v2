<?php

namespace App\Http\Requests\Os;

use Illuminate\Foundation\Http\FormRequest;

class RespondWorkOrderApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('os.work_order.update');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'notes' => $this->notes === '' ? null : $this->notes,
        ]);
    }

    public function rules(): array
    {
        return [
            'notes' => 'nullable|string|max:2000',
        ];
    }
}
