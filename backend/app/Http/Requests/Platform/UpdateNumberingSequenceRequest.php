<?php

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNumberingSequenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('platform.settings.manage');
    }

    public function rules(): array
    {
        return [
            'prefix' => 'sometimes|string|max:20',
            'next_number' => 'sometimes|integer|min:1',
            'padding' => 'sometimes|integer|min:1|max:10',
        ];
    }
}
