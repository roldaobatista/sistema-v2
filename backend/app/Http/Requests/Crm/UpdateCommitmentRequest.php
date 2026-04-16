<?php

namespace App\Http\Requests\Crm;

use App\Models\Commitment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCommitmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('crm.deal.update');
    }

    public function rules(): array
    {
        return [
            'title' => 'string|max:255',
            'description' => 'nullable|string',
            'status' => [Rule::in(array_keys(Commitment::STATUSES))],
            'due_date' => 'nullable|date',
            'priority' => [Rule::in(array_keys(Commitment::PRIORITIES))],
            'completion_notes' => 'nullable|string',
        ];
    }
}
