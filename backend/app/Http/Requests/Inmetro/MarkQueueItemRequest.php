<?php

namespace App\Http\Requests\Inmetro;

use Illuminate\Foundation\Http\FormRequest;

class MarkQueueItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('inmetro.intelligence.convert');
    }

    public function rules(): array
    {
        return [
            'status' => 'required|in:contacted,skipped,converted',
        ];
    }
}
