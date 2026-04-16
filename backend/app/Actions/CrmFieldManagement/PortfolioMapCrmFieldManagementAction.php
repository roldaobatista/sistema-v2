<?php

namespace App\Actions\CrmFieldManagement;

use App\Models\Customer;
use App\Models\User;

class PortfolioMapCrmFieldManagementAction extends BaseCrmFieldManagementAction
{
    /**
     * @param  array<string, mixed>  $data
     * @return mixed
     */
    public function execute(array $data, User $user, int $tenantId)
    {

        $tenantId = $tenantId;
        $q = Customer::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->select('id', 'name', 'latitude', 'longitude', 'address_city', 'address_state',
                'rating', 'health_score', 'last_contact_at', 'segment', 'assigned_seller_id');

        if (isset($data['seller_id'])) {
            $q->where('assigned_seller_id', $data['seller_id']);
        }
        if (isset($data['rating'])) {
            $q->where('rating', $data['rating']);
        }
        if (isset($data['segment'])) {
            $q->where('segment', $data['segment']);
        }

        $perPage = min((int) ($data['per_page'] ?? 50), 200);
        $paginated = $q->paginate($perPage);

        $paginated->getCollection()->transform(function ($c) {
            $daysSinceContact = $c->last_contact_at ? (int) $c->last_contact_at->diffInDays(now()) : 999;
            $c->setAttribute('days_since_contact', $daysSinceContact);
            $c->setAttribute('alert_level', $daysSinceContact > 90 ? 'critical' : ($daysSinceContact > 60 ? 'warning' : ($daysSinceContact > 30 ? 'attention' : 'ok')));

            return $c;
        });

        return $paginated;
    }
}
