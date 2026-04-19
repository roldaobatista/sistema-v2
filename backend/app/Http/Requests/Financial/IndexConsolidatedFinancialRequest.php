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
            // Filtro de tenant (não confundir com tenant_id do escopo de dados — Lei H1).
            // Aceita `tenant_filter` (nome semântico novo) ou `tenant_id` (legado, mantido
            // por compat com frontend existente). Validação efetiva (in_array contra os
            // tenants do user) é feita no controller — ver ConsolidatedFinancialController.
            'tenant_filter' => ['sometimes', 'integer', 'min:1'],
            'tenant_id' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
