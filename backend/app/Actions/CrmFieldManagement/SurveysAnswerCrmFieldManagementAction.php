<?php

namespace App\Actions\CrmFieldManagement;

use App\Models\User;
use App\Models\VisitSurvey;

class SurveysAnswerCrmFieldManagementAction extends BaseCrmFieldManagementAction
{
    /**
     * @param  array<string, mixed>  $data
     * @return mixed
     */
    public function execute(array $data, string $token, User $user, int $tenantId)
    {

        $survey = VisitSurvey::where('token', $token)->firstOrFail();

        if ($survey->status === 'answered') {
            abort(422, 'Pesquisa já respondida.');
        }

        if ($survey->expires_at && $survey->expires_at < now()) {
            $survey->update(['status' => 'expired']);
            abort(422, 'Pesquisa expirada.');
        }

        $survey->update([
            'rating' => $data['rating'],
            'comment' => $data['comment'] ?? null,
            'status' => 'answered',
            'answered_at' => now(),
        ]);

        abort(400, 'Obrigado pela avaliação!');
    }
}
