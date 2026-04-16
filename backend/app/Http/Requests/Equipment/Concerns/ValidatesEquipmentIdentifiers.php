<?php

namespace App\Http\Requests\Equipment\Concerns;

use App\Models\Customer;
use App\Models\Equipment;
use Closure;

trait ValidatesEquipmentIdentifiers
{
    protected function normalizeEquipmentIdentifiers(): void
    {
        $merge = [];

        foreach (['serial_number', 'inmetro_number', 'tag'] as $field) {
            $value = $this->input($field);

            if (! is_string($value)) {
                continue;
            }

            $trimmed = trim($value);
            $merge[$field] = $trimmed === '' ? null : $trimmed;
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    protected function duplicateEquipmentIdentifierRule(
        string $column,
        string $label,
        int $tenantId,
        ?int $ignoreEquipmentId = null
    ): Closure {
        return function (string $attribute, mixed $value, Closure $fail) use ($column, $label, $tenantId, $ignoreEquipmentId): void {
            if (! is_string($value) || trim($value) === '') {
                return;
            }

            $duplicate = Equipment::query()
                ->with('customer:id,name')
                ->where('tenant_id', $tenantId)
                ->whereNull('deleted_at')
                ->where($column, trim($value))
                ->when($ignoreEquipmentId, fn ($query) => $query->whereKeyNot($ignoreEquipmentId))
                ->first();

            if (! $duplicate) {
                return;
            }

            $currentCustomerId = $this->filled('customer_id') ? (int) $this->input('customer_id') : null;
            $duplicateCustomerId = $duplicate->customer_id ? (int) $duplicate->customer_id : null;
            $duplicateCustomer = $duplicate->customer;
            $duplicateCustomerName = $duplicateCustomer instanceof Customer
                ? trim((string) $duplicateCustomer->name)
                : 'outro cliente';

            if ($currentCustomerId !== null && $duplicateCustomerId === $currentCustomerId) {
                $fail("Ja existe um equipamento deste cliente com este {$label}.");

                return;
            }

            $fail("Ja existe um equipamento cadastrado com este {$label} para o cliente {$duplicateCustomerName}. Se for o mesmo equipamento, edite ou transfira o cadastro existente.");
        };
    }
}
