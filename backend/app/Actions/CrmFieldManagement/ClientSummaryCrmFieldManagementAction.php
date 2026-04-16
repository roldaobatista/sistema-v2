<?php

namespace App\Actions\CrmFieldManagement;

use App\Models\Commitment;
use App\Models\CrmActivity;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\Quote;
use App\Models\User;

class ClientSummaryCrmFieldManagementAction extends BaseCrmFieldManagementAction
{
    /**
     * @param  array<string, mixed>  $data
     * @return mixed
     */
    public function execute(array $data, Customer $customer, User $user, int $tenantId)
    {

        $tenantId = $tenantId;
        if ($customer->tenant_id !== $tenantId) {
            abort(404);
        }

        $contacts = $customer->contacts()->get(['name', 'role', 'phone', 'email', 'is_primary']);

        $recentActivities = CrmActivity::where('customer_id', $customer->id)
            ->where('tenant_id', $tenantId)
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->take(5)
            ->get(['type', 'title', 'created_at', 'user_id', 'outcome']);

        $pendingCommitments = Commitment::where('customer_id', $customer->id)
            ->where('tenant_id', $tenantId)
            ->where('status', 'pending')
            ->get(['title', 'due_date', 'responsible_type', 'priority']);

        $equipmentsDue = Equipment::where('customer_id', $customer->id)
            ->whereNotNull('next_calibration_at')
            ->where('next_calibration_at', '<=', now()->addDays(60))
            ->get(['code', 'brand', 'model', 'next_calibration_at']);

        $pendingQuotes = Quote::where('customer_id', $customer->id)
            ->where('tenant_id', $tenantId)
            ->where('status', 'pending')
            ->get(['quote_number', 'total', 'created_at']);

        return [
            'customer' => $customer->only([
                'id', 'name', 'document', 'phone', 'email', 'address_city',
                'address_state', 'rating', 'health_score', 'segment',
                'last_contact_at', 'next_follow_up_at', 'contract_type',
            ]),
            'contacts' => $contacts,
            'recent_activities' => $recentActivities,
            'pending_commitments' => $pendingCommitments,
            'equipments_due' => $equipmentsDue,
            'pending_quotes' => $pendingQuotes,
        ];
    }
}
