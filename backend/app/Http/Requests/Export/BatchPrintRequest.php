<?php

namespace App\Http\Requests\Export;

use App\Models\Quote;
use App\Models\WorkOrder;
use Illuminate\Foundation\Http\FormRequest;

class BatchPrintRequest extends FormRequest
{
    public function authorize(): bool
    {
        $entity = $this->input('entity');

        if ($entity === 'work_orders') {
            return $this->user()->can('os.work_order.view');
        }

        if ($entity === 'quotes') {
            return $this->user()->can('quotes.quote.view');
        }

        return false;
    }

    public function rules(): array
    {
        return [
            'entity' => 'required|string|in:work_orders,quotes',
            'ids' => 'required|array|min:1|max:50',
            'ids.*' => [
                'integer',
                function ($attribute, $value, $fail) {
                    $entity = $this->input('entity');
                    $modelClass = $entity === 'work_orders' ? WorkOrder::class : ($entity === 'quotes' ? Quote::class : null);

                    if ($modelClass) {
                        $user = $this->user();
                        $tenantId = (int) ($user->current_tenant_id ?? $user->tenant_id);

                        $exists = $modelClass::where('id', $value)
                            ->where('tenant_id', $tenantId)
                            ->exists();

                        if (! $exists) {
                            $fail('Documento não encontrado ou sem permissão de acesso.');
                        }
                    }
                },
            ],
        ];
    }
}
