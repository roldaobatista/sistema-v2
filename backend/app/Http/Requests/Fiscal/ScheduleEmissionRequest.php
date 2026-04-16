<?php

namespace App\Http\Requests\Fiscal;

use Illuminate\Foundation\Http\FormRequest;

class ScheduleEmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('fiscal.note.create');
    }

    protected function prepareForValidation(): void
    {
        $cleaned = [];
        foreach (['work_order_id', 'quote_id'] as $field) {
            if ($this->has($field) && $this->input($field) === '') {
                $cleaned[$field] = null;
            }
        }
        if ($cleaned) {
            $this->merge($cleaned);
        }
    }

    public function rules(): array
    {
        return [
            'type' => 'required|in:nfe,nfse',
            'customer_id' => 'required|integer',
            'scheduled_at' => 'required|date|after:now',
            'work_order_id' => 'nullable|integer',
            'quote_id' => 'nullable|integer',
        ];
    }
}
