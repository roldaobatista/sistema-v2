<?php

namespace App\Http\Requests\Lookup;

use App\Support\LookupValidationRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateLookupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('lookups.update');
    }

    public function rules(): array
    {
        $type = (string) $this->route('type');
        $id = $this->route('id') ? (int) $this->route('id') : null;
        $tenantId = $this->user() ? (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id ?? 0) : null;

        return LookupValidationRules::rules($type, $id, $tenantId);
    }
}
