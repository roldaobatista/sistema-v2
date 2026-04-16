<?php

namespace App\Http\Controllers\Api\V1\Crm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\StoreCrmDealCompetitorRequest;
use App\Http\Requests\Crm\StoreLossReasonRequest;
use App\Http\Requests\Crm\UpdateCrmDealCompetitorRequest;
use App\Http\Requests\Crm\UpdateLossReasonRequest;
use App\Models\CrmDeal;
use App\Models\CrmDealCompetitor;
use App\Models\CrmLossReason;
use App\Models\Customer;
use App\Models\Equipment;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CrmIntelligenceController extends Controller
{
    private function tenantId(Request $request): int
    {
        $user = $request->user();

        return (int) ($user->current_tenant_id ?? $user->tenant_id);
    }

    // ── Cross-sell / Up-sell ──────────────────────────────

    public function crossSellRecommendations(Request $request, int $customerId): JsonResponse
    {
        abort_unless($request->user()->can('crm.deal.view'), 403);

        $tenantId = $this->tenantId($request);
        $customer = Customer::where('tenant_id', $tenantId)->findOrFail($customerId);

        $customerEquipCount = Equipment::where('customer_id', $customerId)->count();
        $calibratedCount = Equipment::where('customer_id', $customerId)->whereNotNull('last_calibration_at')->count();

        $recommendations = [];

        if ($customerEquipCount > $calibratedCount) {
            $uncalibrated = $customerEquipCount - $calibratedCount;
            $recommendations[] = [
                'type' => 'cross_sell', 'title' => "Calibrar {$uncalibrated} equipamento(s) pendente(s)",
                'description' => "Cliente possui {$customerEquipCount} equipamentos mas apenas {$calibratedCount} são calibrados regularmente.",
                'estimated_value' => $uncalibrated * 150, 'priority' => 'high',
            ];
        }

        if (empty($customer->contract_type) || $customer->contract_type === 'avulso') {
            $annualSpend = CrmDeal::where('tenant_id', $tenantId)->where('customer_id', $customerId)->won()->where('won_at', '>=', now()->subYear())->sum('value');
            if ($annualSpend > 1000) {
                $recommendations[] = [
                    'type' => 'up_sell', 'title' => 'Propor contrato anual',
                    'description' => 'Cliente gasta R$ '.number_format($annualSpend, 2, ',', '.').'/ano em serviços avulsos. Contrato anual pode economizar 15-20%.',
                    'estimated_value' => $annualSpend * 0.85, 'priority' => 'high',
                ];
            }
        }

        $segment = $customer->segment;
        if ($segment) {
            $popularServices = CrmDeal::where('tenant_id', $tenantId)
                ->whereHas('customer', fn ($q) => $q->where('segment', $segment)->where('id', '!=', $customerId))
                ->won()->where('won_at', '>=', now()->subYear())
                ->select('source', DB::raw('COUNT(*) as cnt'), DB::raw('AVG(value) as avg_value'))
                ->groupBy('source')->orderByDesc('cnt')->limit(3)->get();

            $customerExistingSources = CrmDeal::where('tenant_id', $tenantId)->where('customer_id', $customerId)->won()->whereNotNull('source')->pluck('source')->unique();

            foreach ($popularServices as $svc) {
                if (! $customerExistingSources->contains($svc->source) && $svc->source) {
                    $recommendations[] = [
                        'type' => 'cross_sell', 'title' => 'Serviço popular no segmento: '.(CrmDeal::SOURCES[$svc->source] ?? $svc->source),
                        'description' => "{$svc->cnt} clientes do mesmo segmento utilizam este serviço.",
                        'estimated_value' => round($svc->avg_value, 2), 'priority' => 'medium',
                    ];
                }
            }
        }

        return ApiResponse::data($recommendations);
    }

    // ── Loss Reasons ──────────────────────────────────────

    public function lossReasons(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('crm.deal.view'), 403);

        return ApiResponse::paginated(
            CrmLossReason::where('tenant_id', $this->tenantId($request))
                ->orderBy('sort_order')
                ->paginate(min((int) $request->input('per_page', 50), 100))
        );
    }

    public function storeLossReason(StoreLossReasonRequest $request): JsonResponse
    {
        $data = $request->validated();

        return ApiResponse::data(CrmLossReason::create([...$data, 'tenant_id' => $this->tenantId($request)]), 201);
    }

    public function updateLossReason(UpdateLossReasonRequest $request, CrmLossReason $reason): JsonResponse
    {
        $reason->update($request->validated());

        return ApiResponse::data($reason);
    }

    public function lossAnalytics(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('crm.deal.view'), 403);

        $tenantId = $this->tenantId($request);
        $months = $request->input('months', 6);
        $since = now()->subMonths($months);

        try {
            $byReason = CrmDeal::where('crm_deals.tenant_id', $tenantId)
                ->where('crm_deals.status', CrmDeal::STATUS_LOST)->where('crm_deals.lost_at', '>=', $since)
                ->whereNotNull('crm_deals.loss_reason_id')->whereNull('crm_deals.deleted_at')
                ->join('crm_loss_reasons', 'crm_deals.loss_reason_id', '=', 'crm_loss_reasons.id')
                ->select('crm_loss_reasons.name', 'crm_loss_reasons.category', DB::raw('COUNT(*) as count'), DB::raw('SUM(crm_deals.value) as total_value'))
                ->groupBy('crm_loss_reasons.name', 'crm_loss_reasons.category')->orderByDesc('count')->get();

            $byCompetitor = CrmDeal::where('crm_deals.tenant_id', $tenantId)->lost()->where('lost_at', '>=', $since)->whereNotNull('competitor_name')
                ->select('competitor_name', DB::raw('COUNT(*) as count'), DB::raw('SUM(value) as total_value'), DB::raw('AVG(competitor_price) as avg_competitor_price'))
                ->groupBy('competitor_name')->orderByDesc('count')->get();

            $byUser = CrmDeal::where('crm_deals.tenant_id', $tenantId)
                ->where('crm_deals.status', CrmDeal::STATUS_LOST)->where('crm_deals.lost_at', '>=', $since)->whereNull('crm_deals.deleted_at')
                ->join('users', 'crm_deals.assigned_to', '=', 'users.id')
                ->select('users.name', DB::raw('COUNT(*) as count'), DB::raw('SUM(crm_deals.value) as total_value'))
                ->groupBy('users.name')->orderByDesc('count')->get();

            $isSqlite = DB::connection()->getDriverName() === 'sqlite';
            $monthExpr = $isSqlite ? "strftime('%Y-%m', lost_at)" : "DATE_FORMAT(lost_at, '%Y-%m')";

            $monthlyTrend = CrmDeal::where('tenant_id', $tenantId)->lost()->where('lost_at', '>=', $since)
                ->selectRaw("{$monthExpr} as month, COUNT(*) as count, SUM(value) as total_value")
                ->groupByRaw($monthExpr)->orderBy('month')->get();
        } catch (\Exception $e) {
            Log::warning('CrmIntelligence lossAnalytics query failed', ['error' => $e->getMessage()]);

            return ApiResponse::data(['by_reason' => [], 'by_competitor' => [], 'by_user' => [], 'monthly_trend' => []]);
        }

        return ApiResponse::data(['by_reason' => $byReason, 'by_competitor' => $byCompetitor, 'by_user' => $byUser, 'monthly_trend' => $monthlyTrend]);
    }

    // ── Revenue Intelligence ──────────────────────────────

    public function revenueIntelligence(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('crm.deal.view'), 403);

        $tenantId = $this->tenantId($request);

        $contractCustomers = Customer::where('tenant_id', $tenantId)->where('is_active', true)->whereNotNull('contract_type')->where('contract_type', '!=', 'avulso')->count();

        $mrr = CrmDeal::where('tenant_id', $tenantId)->won()
            ->whereHas('customer', fn ($q) => $q->whereNotNull('contract_type')->where('contract_type', '!=', 'avulso'))
            ->where('won_at', '>=', now()->subYear())->avg('value') ?? 0;

        $oneTimeRevenue = CrmDeal::where('tenant_id', $tenantId)->won()->where('won_at', '>=', now()->startOfMonth())
            ->whereHas('customer', fn ($q) => $q->where(function ($q2) {
                $q2->whereNull('contract_type')->orWhere('contract_type', 'avulso');
            }))->sum('value');

        $totalActiveStart = Customer::where('tenant_id', $tenantId)->where('created_at', '<=', now()->subMonth()->startOfMonth())->where('is_active', true)->count();
        $churned = Customer::where('tenant_id', $tenantId)->where('is_active', false)->where('updated_at', '>=', now()->subMonth()->startOfMonth())->count();
        $churnRate = $totalActiveStart > 0 ? round(($churned / $totalActiveStart) * 100, 1) : 0;

        $avgDealValue = CrmDeal::where('tenant_id', $tenantId)->won()->avg('value') ?? 0;
        $avgDealsPerCustomer = CrmDeal::where('tenant_id', $tenantId)->won()
            ->select('customer_id', DB::raw('COUNT(*) as cnt'))->groupBy('customer_id')->get()->avg('cnt') ?? 1;
        $ltv = bcmul((string) $avgDealValue, (string) $avgDealsPerCustomer, 2);

        $isSqlite = DB::connection()->getDriverName() === 'sqlite';
        $monthExpr = $isSqlite ? "strftime('%Y-%m', won_at)" : "DATE_FORMAT(won_at, '%Y-%m')";

        $monthlyRevenue = CrmDeal::where('tenant_id', $tenantId)->won()->where('won_at', '>=', now()->subMonths(12))
            ->selectRaw("{$monthExpr} as month, SUM(value) as revenue, COUNT(*) as deals")
            ->groupByRaw($monthExpr)->orderBy('month')->get();

        $bySegment = CrmDeal::where('crm_deals.tenant_id', $tenantId)->where('crm_deals.status', 'won')
            ->whereNotNull('crm_deals.won_at')->where('crm_deals.won_at', '>=', now()->subYear())->whereNull('crm_deals.deleted_at')
            ->join('customers', 'crm_deals.customer_id', '=', 'customers.id')
            ->select('customers.segment', DB::raw('SUM(crm_deals.value) as revenue'), DB::raw('COUNT(*) as deals'))
            ->groupBy('customers.segment')->orderByDesc('revenue')->get();

        return ApiResponse::data([
            'mrr' => round($mrr, 2), 'contract_customers' => $contractCustomers, 'one_time_revenue' => round($oneTimeRevenue, 2),
            'churn_rate' => $churnRate, 'ltv' => round($ltv, 2), 'avg_deal_value' => round($avgDealValue, 2),
            'monthly_revenue' => $monthlyRevenue, 'by_segment' => $bySegment,
        ]);
    }

    // ── Competitive Matrix ────────────────────────────────

    public function competitiveMatrix(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('crm.deal.view'), 403);

        $tenantId = $this->tenantId($request);
        $months = $request->input('months', 12);
        $since = now()->subMonths($months);

        if ($request->boolean('detailed')) {
            $entries = CrmDealCompetitor::query()
                ->whereHas('deal', function ($query) use ($tenantId, $since) {
                    $query->where('tenant_id', $tenantId)->where('created_at', '>=', $since)->whereNull('deleted_at');
                })
                ->with(['deal:id,title,value,status,customer_id', 'deal.customer:id,name'])
                ->orderByDesc('created_at')
                ->paginate(min((int) $request->input('per_page', 50), 100));

            return ApiResponse::paginated($entries);
        }

        $competitors = CrmDealCompetitor::join('crm_deals', 'crm_deal_competitors.deal_id', '=', 'crm_deals.id')
            ->where('crm_deals.tenant_id', $tenantId)->where('crm_deals.created_at', '>=', $since)->whereNull('crm_deals.deleted_at')
            ->select('crm_deal_competitors.competitor_name',
                DB::raw('COUNT(*) as total_encounters'),
                DB::raw('SUM(CASE WHEN crm_deals.status = "won" THEN 1 ELSE 0 END) as wins'),
                DB::raw('SUM(CASE WHEN crm_deals.status = "lost" THEN 1 ELSE 0 END) as losses'),
                DB::raw('AVG(crm_deal_competitors.competitor_price) as avg_price'),
                DB::raw('AVG(crm_deals.value) as our_avg_price'))
            ->groupBy('crm_deal_competitors.competitor_name')->orderByDesc('total_encounters')->get()
            ->map(function ($c) {
                $total = $c->wins + $c->losses;
                $c->win_rate = $total > 0 ? round(($c->wins / $total) * 100, 1) : 0;
                $c->price_diff = $c->avg_price && $c->our_avg_price ? round((($c->our_avg_price - $c->avg_price) / $c->avg_price) * 100, 1) : null;

                return $c;
            });

        return ApiResponse::data($competitors);
    }

    public function competitorOptions(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('crm.deal.view'), 403);

        $tenantId = $this->tenantId($request);
        $deals = CrmDeal::where('tenant_id', $tenantId)->with('customer:id,name')
            ->select('id', 'title', 'value', 'status', 'customer_id')->orderByDesc('updated_at')->limit(250)->get()
            ->map(fn (CrmDeal $deal) => [
                'id' => (int) $deal->id, 'title' => $deal->title, 'status' => $deal->status,
                'value' => (float) ($deal->value ?? 0), 'customer_id' => $deal->customer_id, 'customer_name' => $deal->customer?->name,
            ]);

        return ApiResponse::data(['deals' => $deals]);
    }

    public function storeDealCompetitor(StoreCrmDealCompetitorRequest $request): JsonResponse
    {
        $data = $request->validated();
        $competitor = CrmDealCompetitor::create([...$data, 'outcome' => $data['outcome'] ?? 'unknown']);

        return ApiResponse::data($competitor->load('deal:id,title,value,status,customer_id'), 201);
    }

    public function updateDealCompetitor(UpdateCrmDealCompetitorRequest $request, CrmDealCompetitor $competitor): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        $competitor->loadMissing('deal:id,tenant_id');
        if ((int) ($competitor->deal?->tenant_id ?? 0) !== $tenantId) {
            return ApiResponse::message('Registro de concorrente não encontrado', 404);
        }
        $competitor->update($request->validated());

        return ApiResponse::data($competitor->fresh()->load('deal:id,title,value,status,customer_id'));
    }

    public function destroyDealCompetitor(Request $request, CrmDealCompetitor $competitor): JsonResponse
    {
        abort_unless($request->user()->can('crm.deal.delete'), 403);

        $tenantId = $this->tenantId($request);
        $competitor->loadMissing('deal:id,tenant_id');
        if ((int) ($competitor->deal?->tenant_id ?? 0) !== $tenantId) {
            return ApiResponse::message('Registro de concorrente não encontrado', 404);
        }

        try {
            $competitor->delete();

            return ApiResponse::message('Concorrente removido com sucesso');
        } catch (\Exception $e) {
            Log::error('CrmIntelligence destroyDealCompetitor failed', ['competitor_id' => $competitor->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao remover concorrente', 500);
        }
    }
}
