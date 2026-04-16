<?php

namespace App\Http\Requests\HR;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePerformanceReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('hr.performance.manage');
    }

    public function rules(): array
    {
        return [
            'status' => 'sometimes|in:scheduled,in_progress,completed,canceled',
            'content' => 'nullable|array',
            'ratings' => 'nullable|array',
            'overall_rating' => 'nullable|numeric',
            'feedback_text' => 'nullable|string',
            'okrs' => 'nullable|array',
            'nine_box_potential' => 'nullable|integer',
            'nine_box_performance' => 'nullable|integer',
            'action_plan' => 'nullable|string',
        ];
    }
}
