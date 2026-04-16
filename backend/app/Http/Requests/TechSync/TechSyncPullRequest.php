<?php

namespace App\Http\Requests\TechSync;

use Illuminate\Foundation\Http\FormRequest;

class TechSyncPullRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('os.work_order.view');
    }

    /**
     * @return array<int|string, mixed>
     */
    public function rules(): array
    {
        return [
            'since' => ['nullable', 'date'],
        ];
    }
}
