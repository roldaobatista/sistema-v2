<?php

namespace App\Actions\CrmFieldManagement;

use App\Models\Commitment;
use App\Models\Customer;
use App\Models\User;
use App\Models\VisitReport;
use Illuminate\Support\Arr;

class ReportsStoreCrmFieldManagementAction extends BaseCrmFieldManagementAction
{
    /**
     * @param  array<string, mixed>  $data
     * @return mixed
     */
    public function execute(array $data, User $user, int $tenantId)
    {

        $tenantId = $tenantId;

        $report = VisitReport::create([
            'tenant_id' => $tenantId,
            'user_id' => $user->id,
            ...Arr::except($data, ['commitments']),
        ]);

        if (! empty($data['commitments'])) {
            foreach ($data['commitments'] as $c) {
                Commitment::create([
                    'tenant_id' => $tenantId,
                    'customer_id' => $data['customer_id'],
                    'user_id' => $user->id,
                    'visit_report_id' => $report->id,
                    'title' => $c['title'],
                    'responsible_type' => $c['responsible_type'],
                    'due_date' => $c['due_date'] ?? null,
                    'priority' => $c['priority'] ?? 'normal',
                ]);
            }
        }

        if (! empty($data['next_contact_at'])) {
            Customer::where('id', $data['customer_id'])->update([
                'next_follow_up_at' => $data['next_contact_at'],
            ]);
        }

        return $report->load(['customer:id,name', 'user:id,name', 'commitments']);
    }
}
