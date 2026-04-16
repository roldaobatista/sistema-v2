<?php

namespace App\Http\Requests\HR;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOnCallScheduleRequest extends FormRequest
{
    protected array $shiftAliases = [
        'day' => 'full',
        'all_day' => 'full',
        'full_day' => 'full',
    ];

    public function authorize(): bool
    {
        return $this->user()->can('hr.schedule.manage');
    }

    protected function prepareForValidation(): void
    {
        $entries = collect($this->input('entries', []))
            ->map(function ($entry) {
                if (! is_array($entry) || ! isset($entry['shift'])) {
                    return $entry;
                }

                $shift = strtolower(trim((string) $entry['shift']));
                $entry['shift'] = $this->shiftAliases[$shift] ?? $shift;

                return $entry;
            })
            ->all();

        if ($entries !== $this->input('entries')) {
            $this->merge(['entries' => $entries]);
        }
    }

    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);

        return [
            'entries' => 'required|array|min:1',
            'entries.*.user_id' => ['required', 'integer', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'entries.*.date' => 'required|date',
            'entries.*.shift' => 'required|string|in:morning,afternoon,night,full',
        ];
    }

    public function messages(): array
    {
        return [
            'entries.required' => 'Pelo menos um registro de escala é obrigatório.',
        ];
    }
}
