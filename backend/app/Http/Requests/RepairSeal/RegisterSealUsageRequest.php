<?php

namespace App\Http\Requests\RepairSeal;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterSealUsageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('repair_seals.use');
    }

    public function rules(): array
    {
        $tenantId = (int) $this->user()->current_tenant_id;

        return [
            'seal_id' => [
                'required',
                'integer',
                Rule::exists('inmetro_seals', 'id')
                    ->where('tenant_id', $tenantId)
                    ->where('assigned_to', $this->user()->id)
                    ->where('status', 'assigned'),
            ],
            'work_order_id' => ['required', Rule::exists('work_orders', 'id')->where('tenant_id', $tenantId)],
            'equipment_id' => ['required', Rule::exists('equipments', 'id')->where('tenant_id', $tenantId)],
            'photo' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
        ];
    }

    public function messages(): array
    {
        return [
            'seal_id.exists' => 'Selo não encontrado ou não está atribuído a você.',
            'photo.required' => 'Foto do selo aplicado é obrigatória.',
            'photo.image' => 'O arquivo deve ser uma imagem (JPG, PNG, WebP).',
        ];
    }
}
