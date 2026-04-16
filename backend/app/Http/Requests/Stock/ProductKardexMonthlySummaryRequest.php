<?php

declare(strict_types=1);

namespace App\Http\Requests\Stock;

use Illuminate\Foundation\Http\FormRequest;

class ProductKardexMonthlySummaryRequest extends FormRequest
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
            'months' => ['sometimes', 'integer', 'min:1', 'max:24'],
        ];
    }
}
