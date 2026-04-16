<?php

namespace App\Http\Requests\Crm;

use Illuminate\Foundation\Http\FormRequest;

class DealsMarkLostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('crm.deal.update');
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('reason') && ! $this->has('lost_reason')) {
            $this->merge(['lost_reason' => $this->input('reason')]);
        }

        if ($this->has('lost_reason') && $this->input('lost_reason') === '') {
            $this->merge(['lost_reason' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'lost_reason' => 'nullable|string|max:500',
        ];
    }
}
