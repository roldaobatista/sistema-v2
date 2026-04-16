<?php

namespace App\Http\Requests\Os;

use App\Models\WorkOrder;
use Illuminate\Foundation\Http\FormRequest;

class ResumeDisplacementRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var WorkOrder|null $workOrder */
        $workOrder = $this->route('work_order') ?? $this->route('workOrder');

        if (! $workOrder) {
            return false;
        }

        $user = $this->user();

        if (! $user->can('os.work_order.change_status')) {
            return false;
        }

        $isPrivileged = $user->hasRole('admin') || collect($user->roles ?? [])->contains(fn ($r) => in_array($r->name, ['manager', 'quality_analyst']));

        if ($isPrivileged) {
            return true;
        }

        return $workOrder->isTechnicianAuthorized($user->id);
    }

    /**
     * @return array<int|string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    public function messages(): array
    {
        return [
            'authorize' => 'Você não está autorizado a gerenciar a execução desta OS.',
        ];
    }
}
