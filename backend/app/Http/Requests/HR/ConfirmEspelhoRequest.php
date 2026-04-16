<?php

namespace App\Http\Requests\HR;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmEspelhoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('hr.clock.view')
            || (bool) $this->user()?->can('hr.clock.manage');
    }

    public function rules(): array
    {
        return [
            'year' => 'required|integer',
            'month' => 'required|integer|between:1,12',
            'password' => 'required|string',
        ];
    }
}
