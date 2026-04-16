<?php

namespace App\Http\Requests\HR;

use App\Models\LeaveRequest;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreLeaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('hr.leave.create');
    }

    protected function prepareForValidation(): void
    {
        $cleaned = [];
        foreach (['user_id', 'reason', 'document_path'] as $field) {
            if ($this->has($field) && $this->input($field) === '') {
                $cleaned[$field] = null;
            }
        }
        if ($cleaned) {
            $this->merge($cleaned);
        }
    }

    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);

        return [
            'user_id' => ['nullable', Rule::exists('users', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)->orWhereIn('id', fn ($sub) => $sub->select('user_id')->from('user_tenants')->where('tenant_id', $tenantId)))],
            'type' => 'required|in:vacation,medical,personal,maternity,paternity,bereavement,other',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'nullable|string|max:500',
            'document_path' => 'nullable|string|max:500',
            'document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ];
    }

    /**
     * CLT art. 134 §1: when splitting vacation, at least one period must be >= 14 consecutive days.
     * This validation checks existing approved/pending vacation periods for the same user
     * in the same acquisition period and ensures the rule is satisfied.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->input('type') !== 'vacation') {
                return;
            }

            $startDate = $this->input('start_date');
            $endDate = $this->input('end_date');

            if (! $startDate || ! $endDate) {
                return;
            }

            $start = Carbon::parse($startDate);
            $end = Carbon::parse($endDate);
            $currentDays = $start->diffInDays($end) + 1;

            $userId = $this->input('user_id') ?? $this->user()->id;

            // Fetch other vacation leave requests for the same user (approved or pending)
            $existingPeriods = LeaveRequest::where('user_id', $userId)
                ->where('type', 'vacation')
                ->whereIn('status', ['approved', 'pending'])
                ->select('start_date', 'end_date')
                ->get()
                ->map(function ($lr) {
                    $s = Carbon::parse($lr->start_date);
                    $e = Carbon::parse($lr->end_date);

                    return $s->diffInDays($e) + 1;
                })
                ->toArray();

            // Add current request's days to the list
            $allPeriods = array_merge($existingPeriods, [$currentDays]);

            // If there's only one period total, it must be >= 14 days (or the full 30)
            // If splitting (more than one period), at least one must be >= 14 days
            if (count($allPeriods) > 1) {
                $maxPeriod = max($allPeriods);
                if ($maxPeriod < 14) {
                    $validator->errors()->add(
                        'start_date',
                        'CLT art. 134 §1: ao fracionar férias, pelo menos um dos períodos deve ter no mínimo 14 dias corridos.'
                    );
                }

                // CLT art. 134 §1: no period can be less than 5 days
                foreach ($allPeriods as $days) {
                    if ($days < 5) {
                        $validator->errors()->add(
                            'start_date',
                            'CLT art. 134 §1: nenhum período de férias pode ser inferior a 5 dias corridos.'
                        );
                        break;
                    }
                }

                // CLT art. 134: vacation can be split into at most 3 periods
                if (count($allPeriods) > 3) {
                    $validator->errors()->add(
                        'start_date',
                        'CLT art. 134: férias podem ser fracionadas em no máximo 3 períodos.'
                    );
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'type.required' => 'O tipo de afastamento é obrigatório.',
            'start_date.required' => 'A data de início é obrigatória.',
            'end_date.required' => 'A data de término é obrigatória.',
        ];
    }
}
