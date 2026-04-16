<?php

namespace App\Http\Requests\Crm;

use App\Models\CrmInteractiveProposal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RespondToProposalRequest extends FormRequest
{
    /**
     * Validate that the token route parameter resolves to an existing, respondable proposal.
     */
    public function authorize(): bool
    {
        $token = $this->route('token');

        if (empty($token) || ! is_string($token)) {
            return false;
        }

        $proposal = CrmInteractiveProposal::where('token', $token)->first();

        if (! $proposal) {
            return false;
        }

        return true;
    }

    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['accept', 'reject'])],
            'client_notes' => 'nullable|string',
            'client_signature' => 'nullable|string',
            'item_interactions' => 'nullable|array',
        ];
    }
}
