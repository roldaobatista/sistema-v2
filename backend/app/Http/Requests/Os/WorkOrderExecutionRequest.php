<?php

namespace App\Http\Requests\Os;

use App\Models\Role;
use App\Models\WorkOrder;
use App\Support\ApiResponse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class WorkOrderExecutionRequest extends FormRequest
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
            throw new HttpResponseException(
                ApiResponse::message('Voce nao tem permissao para executar o fluxo desta OS.', 403)
            );
        }

        if ($user->hasAnyRole([Role::SUPER_ADMIN, Role::ADMIN, Role::GERENTE])) {
            return true;
        }

        if (! $workOrder->isTechnicianAuthorized($user->id)) {
            throw new HttpResponseException(
                ApiResponse::message('Você não está autorizado a gerenciar a execução desta OS.', 403)
            );
        }

        return true;
    }

    /**
     * @return array<int|string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
