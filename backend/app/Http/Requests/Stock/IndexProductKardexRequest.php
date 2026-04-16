<?php

declare(strict_types=1);

namespace App\Http\Requests\Stock;

use Illuminate\Foundation\Http\FormRequest;

class IndexProductKardexRequest extends FormRequest
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
            'warehouse_id' => ['sometimes', 'integer', 'min:1'],
            'type' => ['sometimes', 'string', 'max:50'],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date', 'after_or_equal:from'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:200'],
        ];
    }
}
