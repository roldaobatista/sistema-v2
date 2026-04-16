<?php

namespace App\Http\Requests\Advanced;

use Illuminate\Foundation\Http\FormRequest;

class CompleteFollowUpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('commercial.followup.manage');
    }

    public function rules(): array
    {
        return [
            'result' => 'required|in:interested,not_now,lost,converted',
            'notes' => 'nullable|string',
        ];
    }
}
