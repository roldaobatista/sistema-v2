<?php

namespace App\Services\Crm;

use App\Models\CrmDeal;
use App\Models\CrmSmartAlert;
use App\Models\Customer;
use App\Models\Equipment;

class CrmSmartAlertGenerator
{
    public function generateForTenant(int $tenantId): int
    {
        $generated = 0;

        $expiringEquipments = Equipment::where('tenant_id', $tenantId)
            ->whereNotNull('next_calibration_at')
            ->where('next_calibration_at', '<=', now()->addDays(60))
            ->where('next_calibration_at', '>=', now())
            ->get();

        foreach ($expiringEquipments as $equipment) {
            $exists = CrmSmartAlert::where('tenant_id', $tenantId)
                ->where('type', 'calibration_expiring')
                ->where('equipment_id', $equipment->id)
                ->where('status', 'pending')
                ->exists();

            if ($exists) {
                continue;
            }

            CrmSmartAlert::create([
                'tenant_id' => $tenantId,
                'type' => 'calibration_expiring',
                'priority' => 'high',
                'title' => "Calibração vencendo: {$equipment->code}",
                'description' => "Equipamento {$equipment->brand} {$equipment->model} do cliente vence em ".$equipment->next_calibration_at->format('d/m/Y'),
                'customer_id' => $equipment->customer_id,
                'equipment_id' => $equipment->id,
                'metadata' => ['expiry_date' => $equipment->next_calibration_at->toDateString()],
            ]);
            $generated++;
        }

        $stalledDeals = CrmDeal::where('tenant_id', $tenantId)
            ->open()
            ->where('updated_at', '<=', now()->subDays(15))
            ->get();

        foreach ($stalledDeals as $deal) {
            $exists = CrmSmartAlert::where('tenant_id', $tenantId)
                ->where('type', 'deal_stalled')
                ->where('deal_id', $deal->id)
                ->where('status', 'pending')
                ->exists();

            if ($exists) {
                continue;
            }

            CrmSmartAlert::create([
                'tenant_id' => $tenantId,
                'type' => 'deal_stalled',
                'priority' => 'medium',
                'title' => "Deal parado: {$deal->title}",
                'description' => 'Sem atividade há '.now()->diffInDays($deal->updated_at).' dias',
                'customer_id' => $deal->customer_id,
                'deal_id' => $deal->id,
                'assigned_to' => $deal->assigned_to,
            ]);
            $generated++;
        }

        $customersWithoutContact = Customer::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where(function ($query) {
                $query->where('last_contact_at', '<=', now()->subDays(90))
                    ->orWhereNull('last_contact_at');
            })
            ->get();

        foreach ($customersWithoutContact as $customer) {
            $exists = CrmSmartAlert::where('tenant_id', $tenantId)
                ->where('type', 'no_contact')
                ->where('customer_id', $customer->id)
                ->where('status', 'pending')
                ->exists();

            if ($exists) {
                continue;
            }

            CrmSmartAlert::create([
                'tenant_id' => $tenantId,
                'type' => 'no_contact',
                'priority' => $customer->health_score < 50 ? 'high' : 'medium',
                'title' => "Sem contato: {$customer->name}",
                'description' => 'Cliente sem contato há mais de 90 dias',
                'customer_id' => $customer->id,
                'assigned_to' => $customer->assigned_seller_id,
            ]);
            $generated++;
        }

        $expiringContracts = Customer::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereNotNull('contract_end')
            ->where('contract_end', '<=', now()->addDays(60))
            ->where('contract_end', '>=', now())
            ->get();

        foreach ($expiringContracts as $customer) {
            $exists = CrmSmartAlert::where('tenant_id', $tenantId)
                ->where('type', 'contract_expiring')
                ->where('customer_id', $customer->id)
                ->where('status', 'pending')
                ->exists();

            if ($exists) {
                continue;
            }

            CrmSmartAlert::create([
                'tenant_id' => $tenantId,
                'type' => 'contract_expiring',
                'priority' => 'high',
                'title' => "Contrato vencendo: {$customer->name}",
                'description' => 'Contrato vence em '.$customer->contract_end->format('d/m/Y'),
                'customer_id' => $customer->id,
                'assigned_to' => $customer->assigned_seller_id,
                'metadata' => ['contract_end' => $customer->contract_end->toDateString()],
            ]);
            $generated++;
        }

        return $generated;
    }
}
