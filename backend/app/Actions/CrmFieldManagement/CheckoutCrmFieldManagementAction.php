<?php

namespace App\Actions\CrmFieldManagement;

use App\Models\CrmActivity;
use App\Models\User;
use App\Models\VisitCheckin;

class CheckoutCrmFieldManagementAction extends BaseCrmFieldManagementAction
{
    /**
     * @param  array<string, mixed>  $data
     * @return mixed
     */
    public function execute(array $data, VisitCheckin $checkin, User $user, int $tenantId)
    {

        if ($checkin->tenant_id !== $tenantId) {
            abort(403);
        }
        if ($checkin->status !== 'checked_in') {
            abort(422, 'Checkin já finalizado.');
        }

        $checkin->checkout(
            $data['checkout_lat'] ?? null,
            $data['checkout_lng'] ?? null,
            null
        );

        if (isset($data['notes'])) {
            $checkin->update(['notes' => $data['notes']]);
        }

        if ($checkin->activity_id) {
            CrmActivity::where('id', $checkin->activity_id)->update([
                'completed_at' => now(),
                'duration_minutes' => $checkin->duration_minutes,
                'outcome' => 'sucesso',
            ]);
        }

        return $checkin->fresh(['customer:id,name', 'user:id,name']);
    }
}
