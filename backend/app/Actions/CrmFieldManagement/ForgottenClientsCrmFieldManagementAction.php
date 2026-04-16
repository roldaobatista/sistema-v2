<?php

namespace App\Actions\CrmFieldManagement;

use App\Models\Customer;
use App\Models\User;

class ForgottenClientsCrmFieldManagementAction extends BaseCrmFieldManagementAction
{
    /**
     * @param  array<string, mixed>  $data
     * @return mixed
     */
    public function execute(array $data, User $user, int $tenantId)
    {

        $tenantId = $tenantId;

        $baseQuery = Customer::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('next_follow_up_at')
                    ->orWhere('next_follow_up_at', '<', now());
            });

        // Stats via aggregate queries (no full table load)
        $totalForgotten = (clone $baseQuery)->count();
        $critical = (clone $baseQuery)->where(function ($q) {
            $q->whereNull('last_contact_at')
                ->orWhere('last_contact_at', '<', now()->subDays(90));
        })->count();
        $high = (clone $baseQuery)
            ->whereNotNull('last_contact_at')
            ->whereBetween('last_contact_at', [now()->subDays(90), now()->subDays(60)])
            ->count();
        $medium = (clone $baseQuery)
            ->whereNotNull('last_contact_at')
            ->whereBetween('last_contact_at', [now()->subDays(60), now()->subDays(30)])
            ->count();

        $stats = [
            'total_forgotten' => $totalForgotten,
            'critical' => $critical,
            'high' => $high,
            'medium' => $medium,
        ];

        // Paginated customer list
        $perPage = min((int) ($data['per_page'] ?? 25), 100);
        $paginated = (clone $baseQuery)
            ->select('id', 'name', 'rating', 'health_score', 'last_contact_at',
                'next_follow_up_at', 'assigned_seller_id', 'segment', 'address_city')
            ->with(['assignedSeller:id,name'])
            ->orderByDesc('last_contact_at')
            ->paginate($perPage);

        $paginated->getCollection()->transform(function ($c) {
            $daysSinceContact = $c->last_contact_at
                ? (int) $c->last_contact_at->diffInDays(now())
                : 999;
            $c->setAttribute('days_since_contact', $daysSinceContact);
            $c->setAttribute('urgency', $daysSinceContact > 90 ? 'critical'
                : ($daysSinceContact > 60 ? 'high'
                : ($daysSinceContact > 30 ? 'medium' : 'low')));

            return $c;
        });

        return ['paginated' => $paginated, 'extra' => [
            'stats' => $stats,
        ]];
    }
}
