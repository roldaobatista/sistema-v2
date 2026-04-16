<?php

namespace App\Http\Requests\Import;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class XmlImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('estoque.movement.create');
    }

    public function rules(): array
    {
        $tenantId = app('current_tenant_id');

        return [
            'xml_file' => 'required|file|mimes:xml|max:10240',
            'warehouse_id' => [
                'required',
                Rule::exists('warehouses', 'id')->where('tenant_id', $tenantId),
            ],
        ];
    }
}
