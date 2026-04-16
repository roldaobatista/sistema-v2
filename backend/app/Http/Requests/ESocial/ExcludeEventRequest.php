<?php

namespace App\Http\Requests\ESocial;

use Illuminate\Foundation\Http\FormRequest;

class ExcludeEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('hr.esocial.delete');
    }

    public function rules(): array
    {
        return [
            'reason' => 'required|string|max:500',
        ];
    }
}
