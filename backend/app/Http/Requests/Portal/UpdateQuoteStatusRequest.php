<?php

namespace App\Http\Requests\Portal;

use Illuminate\Foundation\Http\FormRequest;

class UpdateQuoteStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('portal.quote.update');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('comments') && $this->input('comments') === '') {
            $this->merge(['comments' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'action' => 'required|in:approve,reject',
            'comments' => 'nullable|string|max:500',
        ];
    }
}
