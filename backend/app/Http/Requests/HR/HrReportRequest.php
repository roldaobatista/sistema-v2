<?php

declare(strict_types=1);

namespace App\Http\Requests\HR;

use Illuminate\Foundation\Http\FormRequest;

class HrReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<int|string, mixed>
     */
    public function rules(): array
    {
        return [
            'months' => ['sometimes', 'integer', 'min:1', 'max:36'],
            'reference_month' => ['sometimes', 'date_format:Y-m'],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'date', 'after_or_equal:start_date'],
        ];
    }
}
