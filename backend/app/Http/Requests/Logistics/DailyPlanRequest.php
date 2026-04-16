<?php

declare(strict_types=1);

namespace App\Http\Requests\Logistics;

use Illuminate\Foundation\Http\FormRequest;

class DailyPlanRequest extends FormRequest
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
            'date' => ['sometimes', 'date'],
        ];
    }
}
