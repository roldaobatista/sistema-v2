<?php

namespace App\Http\Requests\Os;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorkOrderCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('os.work_order.update');
    }

    public function rules(): array
    {
        return [
            'content' => 'required|string|max:5000',
        ];
    }

    public function messages(): array
    {
        return [
            'content.required' => 'O comentario e obrigatorio.',
        ];
    }
}
