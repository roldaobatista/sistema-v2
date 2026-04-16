<?php

namespace App\Actions\CrmFieldManagement;

use App\Models\GamificationBadge;
use App\Models\GamificationScore;
use App\Models\User;

class GamificationDashboardCrmFieldManagementAction extends BaseCrmFieldManagementAction
{
    /**
     * @param  array<string, mixed>  $data
     * @return mixed
     */
    public function execute(array $data, User $user, int $tenantId)
    {

        $tenantId = $tenantId;
        $period = now()->format('Y-m');

        $leaderboard = GamificationScore::where('tenant_id', $tenantId)
            ->where('period', $period)
            ->where('period_type', 'monthly')
            ->with('user:id,name')
            ->orderByDesc('total_points')
            ->get();

        $rank = 1;
        foreach ($leaderboard as $score) {
            $score->rank_position = $rank++;
        }

        $badges = GamificationBadge::where('tenant_id', $tenantId)->active()->get();

        return [
            'period' => $period,
            'leaderboard' => $leaderboard,
            'badges' => $badges,
        ];
    }
}
