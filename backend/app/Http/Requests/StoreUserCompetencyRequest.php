<?php

namespace App\Http\Requests;

use App\Models\UserCompetency;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserCompetencyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', UserCompetency::class)
            || $this->user()->hasRole('admin')
            || $this->user()->is_superadmin;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tenantId = $this->user()?->current_tenant_id;

        return [
            'user_id' => ['required', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'equipment_id' => ['nullable', Rule::exists('equipments', 'id')->where('tenant_id', $tenantId)],
            'supervisor_id' => ['nullable', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'method_name' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:active,expired,suspended,revoked'],
            'issued_at' => ['required', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:issued_at'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
