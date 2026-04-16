<?php

namespace App\Actions\CrmFieldManagement;

use App\Models\Customer;
use App\Models\User;

class PortfolioCoverageCrmFieldManagementAction extends BaseCrmFieldManagementAction
{
    /**
     * @param  array<string, mixed>  $data
     * @return mixed
     */
    public function execute(array $data, User $user, int $tenantId)
    {

        $tenantId = $tenantId;
        $period = ($data['period'] ?? 30);

        $customers = Customer::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->select('id', 'name', 'rating', 'assigned_seller_id', 'last_contact_at')
            ->with('assignedSeller:id,name')
            ->get();

        $visited = $customers->filter(fn ($c) => $c->last_contact_at && $c->last_contact_at->diffInDays(now()) <= $period
        );

        $bySeller = $customers->groupBy(fn ($c) => $c->assignedSeller->name ?? 'Sem vendedor')->map(function ($group) use ($period) {
            $total = $group->count();
            $visited = $group->filter(fn ($c) => $c->last_contact_at && $c->last_contact_at->diffInDays(now()) <= $period)->count();

            return [
                'total' => $total,
                'visited' => $visited,
                'coverage' => $total > 0 ? round(($visited / $total) * 100, 1) : 0,
                'not_visited' => $total - $visited,
            ];
        })->sortByDesc('coverage');

        $byRating = $customers->groupBy('rating')->map(function ($group) use ($period) {
            $total = $group->count();
            $visited = $group->filter(fn ($c) => $c->last_contact_at && $c->last_contact_at->diffInDays(now()) <= $period)->count();

            return [
                'total' => $total,
                'visited' => $visited,
                'coverage' => $total > 0 ? round(($visited / $total) * 100, 1) : 0,
            ];
        });

        return [
            'summary' => [
                'total_clients' => $customers->count(),
                'visited' => $visited->count(),
                'not_visited' => $customers->count() - $visited->count(),
                'coverage_percent' => $customers->count() > 0
                    ? round(($visited->count() / $customers->count()) * 100, 1) : 0,
                'period_days' => $period,
            ],
            'by_seller' => $bySeller,
            'by_rating' => $byRating,
        ];
    }
}
