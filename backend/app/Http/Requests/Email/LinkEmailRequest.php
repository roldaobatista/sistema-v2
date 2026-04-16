<?php

namespace App\Http\Requests\Email;

use Illuminate\Foundation\Http\FormRequest;

class LinkEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('email.inbox.manage');
    }

    public function rules(): array
    {
        return [
            'linked_type' => 'required|string|max:100',
            'linked_id' => 'required|integer',
        ];
    }
}
