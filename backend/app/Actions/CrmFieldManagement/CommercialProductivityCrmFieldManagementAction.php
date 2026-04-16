<?php

namespace App\Actions\CrmFieldManagement;

use App\Models\CrmActivity;
use App\Models\CrmDeal;
use App\Models\Quote;
use App\Models\User;
use App\Models\VisitCheckin;
use Illuminate\Support\Facades\DB;

class CommercialProductivityCrmFieldManagementAction extends BaseCrmFieldManagementAction
{
    /**
     * @param  array<string, mixed>  $data
     * @return mixed
     */
    public function execute(array $data, User $user, int $tenantId)
    {

        $tenantId = $tenantId;
        $period = ($data['period'] ?? 30);
        $since = now()->subDays($period);

        $checkins = VisitCheckin::where('tenant_id', $tenantId)
            ->where('checkin_at', '>=', $since)
            ->where('status', 'checked_out')
            ->select('user_id', DB::raw('COUNT(*) as visits_count'),
                DB::raw('AVG(duration_minutes) as avg_duration'),
                DB::raw('SUM(duration_minutes) as total_duration'))
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        $activities = CrmActivity::where('tenant_id', $tenantId)
            ->where('created_at', '>=', $since)
            ->select('user_id', 'type', DB::raw('COUNT(*) as count'))
            ->groupBy('user_id', 'type')
            ->get();

        $activitiesByUser = $activities->groupBy('user_id')->map(function ($items) {
            return $items->pluck('count', 'type');
        });

        $dealsWon = CrmDeal::where('tenant_id', $tenantId)
            ->won()->where('won_at', '>=', $since)
            ->select('assigned_to', DB::raw('COUNT(*) as count'), DB::raw('SUM(value) as total_value'))
            ->groupBy('assigned_to')
            ->get()
            ->keyBy('assigned_to');

        $quotesGenerated = Quote::where('tenant_id', $tenantId)
            ->where('created_at', '>=', $since)
            ->select('seller_id', DB::raw('COUNT(*) as count'), DB::raw('SUM(total) as total_value'))
            ->groupBy('seller_id')
            ->get()
            ->keyBy('seller_id');

        $sellers = User::whereHas('roles', fn ($q) => $q->whereIn('name', ['comercial', 'vendedor', 'tecnico_vendedor']))
            ->where('tenant_id', $tenantId)
            ->get(['id', 'name']);

        $productivity = $sellers->map(function ($seller) use ($checkins, $activitiesByUser, $dealsWon, $quotesGenerated, $period) {
            $ck = $checkins->get($seller->id);
            $acts = $activitiesByUser->get($seller->id, collect());
            $dw = $dealsWon->get($seller->id);
            $qg = $quotesGenerated->get($seller->id);

            return [
                'user_id' => $seller->id,
                'user_name' => $seller->name,
                'visits' => $ck ? (int) $ck->getAttribute('visits_count') : 0,
                'visits_per_day' => $ck && $period > 0 ? round((float) $ck->getAttribute('visits_count') / $period, 1) : 0,
                'avg_visit_duration' => $ck ? round((float) $ck->getAttribute('avg_duration')) : 0,
                'calls' => (int) ($acts->get('ligacao', 0)),
                'emails' => (int) ($acts->get('email', 0)),
                'whatsapp' => (int) ($acts->get('whatsapp', 0)),
                'total_activities' => $acts->sum(),
                'deals_won' => $dw ? (int) $dw->getAttribute('count') : 0,
                'deals_value' => $dw ? (float) $dw->getAttribute('total_value') : 0,
                'quotes_generated' => $qg ? (int) $qg->getAttribute('count') : 0,
                'quotes_value' => $qg ? (float) $qg->getAttribute('total_value') : 0,
            ];
        })->sortByDesc('visits')->values();

        return [
            'period_days' => $period,
            'sellers' => $productivity,
        ];
    }
}
