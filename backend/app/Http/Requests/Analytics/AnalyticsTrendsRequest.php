<?php

declare(strict_types=1);

namespace App\Http\Requests\Analytics;

use Illuminate\Foundation\Http\FormRequest;

class AnalyticsTrendsRequest extends FormRequest
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
        ];
    }
}
