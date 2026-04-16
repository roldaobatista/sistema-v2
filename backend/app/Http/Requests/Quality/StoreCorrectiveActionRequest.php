<?php

namespace App\Http\Requests\Quality;

use App\Models\CustomerComplaint;
use App\Models\EquipmentCalibration;
use App\Models\QualityAudit;
use App\Models\QualityAuditItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCorrectiveActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('quality.corrective_action.manage');
    }

    protected function prepareForValidation(): void
    {
        $nullable = ['sourceable_type', 'sourceable_id', 'root_cause', 'action_plan', 'responsible_id', 'deadline'];
        $cleaned = [];
        foreach ($nullable as $field) {
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
        $allowedTypes = [
            CustomerComplaint::class,
            EquipmentCalibration::class,
            QualityAudit::class,
            QualityAuditItem::class,
        ];

        return [
            'type' => 'required|in:corrective,preventive',
            'source' => 'required|in:calibration,complaint,audit,internal',
            'sourceable_type' => ['nullable', 'string', Rule::in($allowedTypes)],
            'sourceable_id' => 'nullable|integer|required_with:sourceable_type',
            'nonconformity_description' => 'required|string',
            'root_cause' => 'nullable|string',
            'action_plan' => 'nullable|string',
            'responsible_id' => ['nullable', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'deadline' => 'nullable|date',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $source = $this->input('source');
            $sourceableType = $this->input('sourceable_type');
            $sourceableId = $this->input('sourceable_id');

            if ($source === 'internal') {
                if ($sourceableType !== null || $sourceableId !== null) {
                    $validator->errors()->add('sourceable_type', 'Ações internas não devem informar origem vinculada.');
                }

                return;
            }

            if (! $sourceableType || ! $sourceableId) {
                $validator->errors()->add('sourceable_type', 'A origem vinculada é obrigatória para este tipo de ação.');

                return;
            }

            if (! $this->sourceMatchesType($source, $sourceableType)) {
                $validator->errors()->add('sourceable_type', 'O tipo de origem informado não é compatível com a fonte selecionada.');

                return;
            }

            if (! $this->sourceableExistsForTenant($sourceableType, (int) $sourceableId)) {
                $validator->errors()->add('sourceable_id', 'A origem informada não foi encontrada para a empresa atual.');
            }
        });
    }

    private function sourceMatchesType(string $source, string $sourceableType): bool
    {
        return match ($source) {
            'complaint' => $sourceableType === CustomerComplaint::class,
            'calibration' => $sourceableType === EquipmentCalibration::class,
            'audit' => in_array($sourceableType, [QualityAudit::class, QualityAuditItem::class], true),
            default => false,
        };
    }

    private function sourceableExistsForTenant(string $sourceableType, int $sourceableId): bool
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);

        return match ($sourceableType) {
            CustomerComplaint::class => CustomerComplaint::query()
                ->where('tenant_id', $tenantId)
                ->whereKey($sourceableId)
                ->exists(),
            EquipmentCalibration::class => EquipmentCalibration::query()
                ->where('tenant_id', $tenantId)
                ->whereKey($sourceableId)
                ->exists(),
            QualityAudit::class => QualityAudit::query()
                ->where('tenant_id', $tenantId)
                ->whereKey($sourceableId)
                ->exists(),
            QualityAuditItem::class => QualityAuditItem::query()
                ->whereKey($sourceableId)
                ->whereIn('quality_audit_id', QualityAudit::query()
                    ->where('tenant_id', $tenantId)
                    ->select('id'))
                ->exists(),
            default => false,
        };
    }
}
