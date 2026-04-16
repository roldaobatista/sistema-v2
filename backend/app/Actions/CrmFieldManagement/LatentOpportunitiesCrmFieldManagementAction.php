<?php

namespace App\Actions\CrmFieldManagement;

use App\Models\Customer;
use App\Models\Equipment;
use App\Models\User;

class LatentOpportunitiesCrmFieldManagementAction extends BaseCrmFieldManagementAction
{
    /**
     * @param  array<string, mixed>  $data
     * @return mixed
     */
    public function execute(array $data, User $user, int $tenantId)
    {

        $tenantId = $tenantId;

        $calibrationExpiring = Equipment::whereHas('customer', fn ($q) => $q->where('tenant_id', $tenantId)->where('is_active', true))
            ->whereNotNull('next_calibration_at')
            ->where('next_calibration_at', '<=', now()->addDays(60))
            ->whereDoesntHave('customer.deals', fn ($q) => $q->where('status', 'open'))
            ->with('customer:id,name,rating')
            ->select('id', 'customer_id', 'code', 'brand', 'model', 'next_calibration_at')
            ->get()
            ->map(fn ($e) => [
                'type' => 'calibration_expiring',
                'customer' => $e->customer,
                'detail' => "{$e->brand} {$e->model} ({$e->code})",
                'date' => $e->next_calibration_at,
                'priority' => $e->next_calibration_at <= now() ? 'high' : 'medium',
            ]);

        $inactiveCustomers = Customer::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where('rating', '!=', 'D')
            ->where(function ($q) {
                $q->whereNull('last_contact_at')
                    ->orWhere('last_contact_at', '<', now()->subDays(120));
            })
            ->whereDoesntHave('deals', fn ($q) => $q->where('status', 'open'))
            ->select('id', 'name', 'rating', 'last_contact_at', 'health_score')
            ->get()
            ->map(fn ($c) => [
                'type' => 'inactive_customer',
                'customer' => $c,
                'detail' => 'Sem contato há '.($c->last_contact_at ? (int) $c->last_contact_at->diffInDays(now()).' dias' : 'nunca'),
                'priority' => $c->rating === 'A' ? 'high' : 'medium',
            ]);

        $pendingRenewals = Customer::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereNotNull('contract_end')
            ->where('contract_end', '<=', now()->addDays(60))
            ->where('contract_end', '>=', now()->subDays(30))
            ->select('id', 'name', 'contract_type', 'contract_end', 'rating')
            ->get()
            ->map(fn ($c) => [
                'type' => 'contract_renewal',
                'customer' => $c,
                'detail' => "Contrato {$c->contract_type} vence em ".$c->contract_end->format('d/m/Y'),
                'priority' => $c->contract_end <= now() ? 'high' : 'medium',
            ]);

        $all = $calibrationExpiring->concat($inactiveCustomers)->concat($pendingRenewals)
            ->sortBy(fn ($o) => $o['priority'] === 'high' ? 0 : 1)
            ->values();

        return [
            'opportunities' => $all,
            'summary' => [
                'calibration_expiring' => $calibrationExpiring->count(),
                'inactive_customers' => $inactiveCustomers->count(),
                'contract_renewals' => $pendingRenewals->count(),
                'total' => $all->count(),
            ],
        ];
    }
}
