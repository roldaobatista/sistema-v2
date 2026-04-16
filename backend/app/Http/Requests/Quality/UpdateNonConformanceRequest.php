<?php

namespace App\Http\Requests\Quality;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNonConformanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('calibration.reading.create');
    }

    public function rules(): array
    {
        return [
            'status' => 'sometimes|string|in:open,investigating,correcting,closed,rejected',
            'corrective_action' => 'sometimes|string',
            'root_cause' => 'sometimes|string',
            'preventive_action' => 'sometimes|string',
        ];
    }
}
