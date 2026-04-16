<?php

namespace App\Http\Requests\Email;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmailTagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('email.tag.manage');
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:50',
            'color' => 'sometimes|string|max:20',
        ];
    }
}
