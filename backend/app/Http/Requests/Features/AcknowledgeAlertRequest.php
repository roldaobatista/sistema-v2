<?php

namespace App\Http\Requests\Features;

use Illuminate\Foundation\Http\FormRequest;

class AcknowledgeAlertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('admin.alert.update');
    }

    public function rules(): array
    {
        return [];
    }
}
