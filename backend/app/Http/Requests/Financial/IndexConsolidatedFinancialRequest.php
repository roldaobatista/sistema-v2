<?php

declare(strict_types=1);

namespace App\Http\Requests\Financial;

use App\Models\AccountPayable;
use Illuminate\Foundation\Http\FormRequest;

class IndexConsolidatedFinancialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', AccountPayable::class) ?? false;
    }

    /**
     * @return array<int|string, mixed>
     */
    public function rules(): array
    {
        return [
            'tenant_id' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
