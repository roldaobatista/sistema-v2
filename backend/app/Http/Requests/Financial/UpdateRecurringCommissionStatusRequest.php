<?php

namespace App\Http\Requests\Financial;

use App\Models\RecurringCommission;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRecurringCommissionStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('commissions.recurring.update');
    }

    public function rules(): array
    {
        $statuses = [
            RecurringCommission::STATUS_ACTIVE,
            RecurringCommission::STATUS_PAUSED,
            RecurringCommission::STATUS_TERMINATED,
        ];

        return [
            'status' => 'required|in:'.implode(',', $statuses),
        ];
    }
}
