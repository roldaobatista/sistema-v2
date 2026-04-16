<?php

namespace App\Http\Requests\Email;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmailNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('email.inbox.manage');
    }

    public function rules(): array
    {
        return [
            'content' => 'required|string|max:10000',
        ];
    }
}
