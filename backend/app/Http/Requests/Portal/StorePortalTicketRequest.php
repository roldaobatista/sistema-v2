<?php

namespace App\Http\Requests\Portal;

use App\Models\ClientPortalUser;
use Illuminate\Foundation\Http\FormRequest;

class StorePortalTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = auth('sanctum')->user();

        return $user instanceof ClientPortalUser && $user->tokenCan('portal:access');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('priority') && $this->input('priority') === '') {
            $this->merge(['priority' => null]);
        }
        if ($this->has('category') && $this->input('category') === '') {
            $this->merge(['category' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'subject' => 'required|string|max:255',
            'description' => 'required|string',
            'priority' => 'nullable|in:low,normal,high,urgent',
            'category' => 'nullable|string|max:50',
        ];
    }
}
