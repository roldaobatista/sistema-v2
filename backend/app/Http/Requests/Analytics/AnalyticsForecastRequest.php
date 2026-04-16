<?php

declare(strict_types=1);

namespace App\Http\Requests\Analytics;

use Illuminate\Foundation\Http\FormRequest;

class AnalyticsForecastRequest extends FormRequest
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
            'metric' => ['sometimes', 'string', 'in:revenue,expenses,os_total'],
            'months' => ['sometimes', 'integer', 'min:1', 'max:12'],
        ];
    }
}
