<?php

namespace App\Actions\CrmFieldManagement;

use App\Models\User;
use App\Models\VisitSurvey;

class SurveysIndexCrmFieldManagementAction extends BaseCrmFieldManagementAction
{
    /**
     * @param  array<string, mixed>  $data
     * @return mixed
     */
    public function execute(array $data, User $user, int $tenantId)
    {

        $tenantId = $tenantId;
        $surveys = VisitSurvey::where('tenant_id', $tenantId)
            ->with(['customer:id,name', 'user:id,name'])
            ->orderByDesc('created_at')
            ->paginate(min((int) ($data['per_page'] ?? 25), 100));

        return $surveys;
    }
}
