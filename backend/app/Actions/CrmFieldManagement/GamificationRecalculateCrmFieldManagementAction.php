<?php

namespace App\Actions\CrmFieldManagement;

use App\Models\Commitment;
use App\Models\CrmActivity;
use App\Models\CrmDeal;
use App\Models\Customer;
use App\Models\GamificationScore;
use App\Models\User;
use App\Models\VisitCheckin;
use App\Models\VisitSurvey;

class GamificationRecalculateCrmFieldManagementAction extends BaseCrmFieldManagementAction
{
    /**
     * @param  array<string, mixed>  $data
     * @return mixed
     */
    public function execute(array $data, User $user, int $tenantId)
    {

        $tenantId = $tenantId;
        $period = now()->format('Y-m');
        $startOfMonth = now()->startOfMonth();

        $sellers = User::whereHas('roles', fn ($q) => $q->whereIn('name', ['comercial', 'vendedor', 'tecnico_vendedor']))
            ->where('tenant_id', $tenantId)
            ->get();

        $totalClients = Customer::where('tenant_id', $tenantId)->where('is_active', true)->count();

        foreach ($sellers as $seller) {
            $visits = VisitCheckin::where('tenant_id', $tenantId)
                ->where('user_id', $seller->id)
                ->where('checkin_at', '>=', $startOfMonth)
                ->where('status', 'checked_out')
                ->count();

            $dealsWon = CrmDeal::where('tenant_id', $tenantId)
                ->where('assigned_to', $seller->id)
                ->won()->where('won_at', '>=', $startOfMonth)
                ->count();

            $dealsValue = CrmDeal::where('tenant_id', $tenantId)
                ->where('assigned_to', $seller->id)
                ->won()->where('won_at', '>=', $startOfMonth)
                ->sum('value');

            $activitiesCount = CrmActivity::where('tenant_id', $tenantId)
                ->where('user_id', $seller->id)
                ->where('created_at', '>=', $startOfMonth)
                ->count();

            $clientsContacted = Customer::where('tenant_id', $tenantId)
                ->where('assigned_seller_id', $seller->id)
                ->where('last_contact_at', '>=', $startOfMonth)
                ->count();

            $myClients = Customer::where('tenant_id', $tenantId)
                ->where('assigned_seller_id', $seller->id)
                ->where('is_active', true)
                ->count();

            $coverage = $myClients > 0 ? round(($clientsContacted / $myClients) * 100, 2) : 0;

            $csatAvg = VisitSurvey::where('tenant_id', $tenantId)
                ->where('user_id', $seller->id)
                ->where('status', 'answered')
                ->where('answered_at', '>=', $startOfMonth)
                ->avg('rating') ?? 0;

            $commitmentsTotal = Commitment::where('tenant_id', $tenantId)
                ->where('user_id', $seller->id)
                ->where('created_at', '>=', $startOfMonth)
                ->count();

            $commitmentsOnTime = Commitment::where('tenant_id', $tenantId)
                ->where('user_id', $seller->id)
                ->where('status', 'completed')
                ->where('completed_at', '>=', $startOfMonth)
                ->where(function ($query) {
                    $query->whereNull('due_date')
                        ->orWhereColumn('completed_at', '<=', 'due_date');
                })
                ->count();

            $totalPoints = ($visits * 10) + ($dealsWon * 50) + ($activitiesCount * 2) +
                (int) ($coverage * 2) + (int) ($csatAvg * 20) + ($commitmentsOnTime * 5);

            GamificationScore::updateOrCreate(
                ['tenant_id' => $tenantId, 'user_id' => $seller->id, 'period' => $period],
                [
                    'period_type' => 'monthly',
                    'visits_count' => $visits,
                    'deals_won' => $dealsWon,
                    'deals_value' => $dealsValue,
                    'new_clients' => 0,
                    'activities_count' => $activitiesCount,
                    'coverage_percent' => $coverage,
                    'csat_avg' => round((float) $csatAvg, 2),
                    'commitments_on_time' => $commitmentsOnTime,
                    'commitments_total' => $commitmentsTotal,
                    'total_points' => $totalPoints,
                ]
            );
        }

        abort(400, 'Gamificação recalculada para '.$sellers->count().' vendedores.');
    }
}
