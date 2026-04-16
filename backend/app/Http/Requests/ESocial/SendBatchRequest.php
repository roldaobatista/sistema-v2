<?php

namespace App\Http\Requests\ESocial;

use Illuminate\Foundation\Http\FormRequest;

class SendBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('hr.esocial.create');
    }

    public function rules(): array
    {
        return [
            'event_ids' => ['required', 'array', 'min:1'],
            'event_ids.*' => ['required', 'integer'],
        ];
    }
}
