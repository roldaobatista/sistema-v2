<?php

namespace App\Http\Requests\Journey;

use Illuminate\Foundation\Http\FormRequest;

class ApproveJourneyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $level = $this->route('level');

        return $level === 'operational'
            ? $this->user()->can('hr.clock.manage')
            : $this->user()->can('hr.payroll.manage');
    }

    /**
     * @return array<int|string, mixed>
     */
    public function rules(): array
    {
        return [
            'notes' => 'nullable|string|max:1000',
        ];
    }
}
