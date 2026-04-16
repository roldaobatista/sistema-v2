<?php

namespace App\Actions\CrmFieldManagement;

use App\Models\User;
use App\Models\VisitSurvey;

class SurveysSendCrmFieldManagementAction extends BaseCrmFieldManagementAction
{
    /**
     * @param  array<string, mixed>  $data
     * @return mixed
     */
    public function execute(array $data, User $user, int $tenantId)
    {

        $survey = VisitSurvey::create([
            'tenant_id' => $tenantId,
            'customer_id' => $data['customer_id'],
            'checkin_id' => $data['checkin_id'] ?? null,
            'user_id' => $user->id,
            'sent_at' => now(),
            'expires_at' => now()->addDays(7),
        ]);

        return $survey;
    }
}
