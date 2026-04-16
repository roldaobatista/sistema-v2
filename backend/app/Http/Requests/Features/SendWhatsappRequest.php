<?php

namespace App\Http\Requests\Features;

use Illuminate\Foundation\Http\FormRequest;

class SendWhatsappRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('admin.whatsapp.send');
    }

    public function rules(): array
    {
        return [
            'phone' => 'required|string',
            'message' => 'required|string|max:4096',
        ];
    }
}
