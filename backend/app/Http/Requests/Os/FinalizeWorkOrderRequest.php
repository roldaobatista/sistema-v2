<?php

namespace App\Http\Requests\Os;

use App\Models\WorkOrder;
use Illuminate\Validation\Validator;

class FinalizeWorkOrderRequest extends WorkOrderExecutionRequest
{
    public function rules(): array
    {
        $workOrder = $this->route('work_order') ?? $this->route('workOrder');

        $technicalReportRule = ['nullable', 'string', 'max:5000'];

        if ($workOrder instanceof WorkOrder && $workOrder->service_type && $workOrder->service_type !== 'diagnostico') {
            $technicalReportRule = ['required', 'string', 'max:5000'];
        }

        return [
            'recorded_at' => ['nullable', 'date'],
            'technical_report' => $technicalReportRule,
            'resolution_notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $workOrder = $this->route('work_order') ?? $this->route('workOrder');

            if (! $workOrder instanceof WorkOrder) {
                return;
            }

            $allowedStatuses = [
                WorkOrder::STATUS_IN_SERVICE,
                WorkOrder::STATUS_SERVICE_PAUSED,
                WorkOrder::STATUS_IN_PROGRESS,
            ];

            if (! in_array($workOrder->status, $allowedStatuses, true)) {
                $validator->errors()->add('status', 'OS precisa estar em serviço, serviço pausado ou em andamento para finalizar.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'recorded_at' => ['nullable', 'date'],
            'technical_report.required' => 'O relatório técnico é obrigatório para este tipo de serviço.',
            'technical_report.max' => 'O relatório técnico não pode exceder 5000 caracteres.',
            'resolution_notes.max' => 'As notas de resolução não podem exceder 5000 caracteres.',
        ];
    }
}
