<?php

namespace App\Http\Requests\Email;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmailTagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('email.tag.manage');
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:50',
            'color' => 'required|string|max:20',
        ];
    }
}
