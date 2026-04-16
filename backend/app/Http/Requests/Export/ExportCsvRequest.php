<?php

namespace App\Http\Requests\Export;

use Illuminate\Foundation\Http\FormRequest;

class ExportCsvRequest extends FormRequest
{
    public function authorize(): bool
    {
        $entity = $this->input('entity');

        $permissions = [
            'customers' => 'cadastros.customer.view',
            'products' => 'cadastros.product.view',
            'services' => 'cadastros.service.view',
            'equipments' => 'equipments.equipment.view',
            'work_orders' => 'os.work_order.export',
            'quotes' => 'quotes.quote.export',
        ];

        if (isset($permissions[$entity])) {
            return $this->user()->can($permissions[$entity]);
        }

        // Entity desconhecida: deixar rules() rejeitar com 422 em vez de 403.
        return true;
    }

    public function rules(): array
    {
        $entities = ['customers', 'products', 'services', 'equipments', 'work_orders', 'quotes'];

        return [
            'entity' => 'required|string|in:'.implode(',', $entities),
            'fields' => 'nullable|array',
            'fields.*' => 'string',
            'ids' => 'nullable|array',
            'ids.*' => 'integer',
            'filters' => 'nullable|array',
        ];
    }
}
