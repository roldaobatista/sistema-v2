<?php

namespace App\Http\Requests\Lookup;

use App\Support\LookupValidationRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreLookupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('lookups.create');
    }

    public function rules(): array
    {
        $type = (string) $this->route('type');
        $tenantId = $this->user() ? (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id ?? 0) : null;

        return LookupValidationRules::rules($type, null, $tenantId);
    }
}
