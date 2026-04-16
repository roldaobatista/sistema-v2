<?php

declare(strict_types=1);

namespace App\Http\Requests\Helpdesk;

use Illuminate\Foundation\Http\FormRequest;

class IndexSlaViolationRequest extends FormRequest
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
        // per_page e apenas validado como positivo; o controller clamp-a para o maximo (100).
        return [
            'per_page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
