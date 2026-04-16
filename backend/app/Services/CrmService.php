<?php

namespace App\Services;

use App\Actions\Crm\BulkUpdateDealsAction;
use App\Actions\Crm\ConvertDealToQuoteAction;
use App\Actions\Crm\ConvertDealToWorkOrderAction;
use App\Actions\Crm\CreateCrmActivityAction;
use App\Actions\Crm\CreateDealAction;
use App\Actions\Crm\DeleteCrmActivityAction;
use App\Actions\Crm\GetCustomer360DataAction;
use App\Actions\Crm\MarkDealAsLostAction;
use App\Actions\Crm\MarkDealAsWonAction;
use App\Actions\Crm\UpdateCrmActivityAction;
use App\Actions\Crm\UpdateDealAction;
use App\Actions\Crm\UpdateDealStageAction;
use App\Http\Resources\CrmActivityResource;
use App\Http\Resources\CrmDealResource;
use App\Http\Resources\CrmPipelineResource;
use App\Models\CrmActivity;
use App\Models\CrmDeal;
use App\Models\CrmMessage;
use App\Models\CrmPipeline;
use App\Models\CrmPipelineStage;
use App\Models\CrmTrackingEvent;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\Lookups\ContractType;
use App\Models\Lookups\CustomerCompanySize;
use App\Models\Lookups\CustomerRating;
use App\Models\Lookups\CustomerSegment;
use App\Models\Lookups\LeadSource;
use App\Models\Lookups\QuoteSource;
use App\Models\User;
use App\Support\ApiResponse;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CrmService
{
    private function dashboardPeriod(array $data): array
    {
        $period = $data['period'] ?? 'month';
        $periodRef = $data['period_ref'] ?? null;

        $now = now();
        if ($periodRef) {
            if (preg_match('/^\d{4}-\d{2}$/', $periodRef)) {
                $start = Carbon::parse($periodRef.'-01');
            } else {
                $start = Carbon::parse($periodRef);
            }
        } else {
            $start = $now->copy();
        }

        if ($period === 'quarter') {
            $start->startOfQuarter();
            $end = $start->copy()->endOfQuarter();
        } elseif ($period === 'year') {
            $start->startOfYear();
            $end = $start->copy()->endOfYear();
        } else {
            $start->startOfMonth();
            $end = $start->copy()->endOfMonth();
        }

        $label = $period === 'month'
            ? $start->translatedFormat('F Y')
            : ($period === 'quarter'
                ? $start->format('Y').' T'.$start->quarter
                : $start->format('Y'));

        return [
            'start' => $start,
            'end' => $end,
            'label' => $label,
            'period' => $period,
        ];
    }

    public function dashboard(array $data, User $user, int $tenantId)
    {

        $range = $this->dashboardPeriod($data);
        $start = $range['start'];
        $end = $range['end'];

        $openDeals = CrmDeal::where('tenant_id', $tenantId)->open()->count();
        $wonInPeriod = CrmDeal::where('tenant_id', $tenantId)->won()
            ->where('won_at', '>=', $start)
            ->where('won_at', '<=', $end)
            ->count();
        $lostInPeriod = CrmDeal::where('tenant_id', $tenantId)->lost()
            ->where('lost_at', '>=', $start)
            ->where('lost_at', '<=', $end)
            ->count();

        $revenueInPipeline = CrmDeal::where('tenant_id', $tenantId)->open()
            ->selectRaw('SUM(value * probability / 100) as weighted_value')
            ->value('weighted_value') ?? 0;

        $wonRevenue = CrmDeal::where('tenant_id', $tenantId)->won()
            ->where('won_at', '>=', $start)
            ->where('won_at', '<=', $end)
            ->sum('value');

        $avgHealthScore = Customer::where('tenant_id', $tenantId)->where('is_active', true)
            ->where('health_score', '>', 0)
            ->avg('health_score') ?? 0;

        $noContact90 = Customer::where('tenant_id', $tenantId)->where('is_active', true)
            ->noContactSince(90)
            ->count();

        $conversionRate = 0;
        $totalClosed = $wonInPeriod + $lostInPeriod;
        if ($totalClosed > 0) {
            $conversionRate = round(($wonInPeriod / $totalClosed) * 100, 1);
        }

        if ($range['period'] === 'quarter') {
            $prevStart = $start->copy()->subQuarter()->startOfQuarter();
            $prevEnd = $prevStart->copy()->endOfQuarter();
        } elseif ($range['period'] === 'year') {
            $prevStart = $start->copy()->subYear()->startOfYear();
            $prevEnd = $prevStart->copy()->endOfYear();
        } else {
            $prevStart = $start->copy()->subMonth()->startOfMonth();
            $prevEnd = $prevStart->copy()->endOfMonth();
        }
        $prevWon = CrmDeal::where('tenant_id', $tenantId)->won()
            ->where('won_at', '>=', $prevStart)->where('won_at', '<=', $prevEnd)->count();
        $prevLost = CrmDeal::where('tenant_id', $tenantId)->lost()
            ->where('lost_at', '>=', $prevStart)->where('lost_at', '<=', $prevEnd)->count();
        $prevWonRevenue = CrmDeal::where('tenant_id', $tenantId)->won()
            ->where('won_at', '>=', $prevStart)->where('won_at', '<=', $prevEnd)->sum('value');

        // Funil por pipeline
        $pipelines = CrmPipeline::where('tenant_id', $tenantId)->active()
            ->with(['stages' => function ($q) {
                $q->withCount('deals')
                    ->withSum('deals', 'value')
                    ->orderBy('sort_order');
            }])
            ->orderBy('sort_order')
            ->get();

        // Deals recentes
        $recentDeals = CrmDeal::where('tenant_id', $tenantId)
            ->with(['customer:id,name', 'stage:id,name,color', 'pipeline:id,name'])
            ->orderByDesc('updated_at')
            ->take(10)
            ->get();

        // Atividades pendentes
        $upcomingActivities = CrmActivity::where('tenant_id', $tenantId)
            ->with(['customer:id,name', 'deal:id,title'])
            ->upcoming()
            ->take(10)
            ->get();

        // Top clientes por receita (deals ganhos no período)
        $topCustDealStats = CrmDeal::where('tenant_id', $tenantId)->won()
            ->where('won_at', '>=', $start)->where('won_at', '<=', $end)
            ->select('customer_id', DB::raw('SUM(value) as total_value'), DB::raw('COUNT(*) as deal_count'))
            ->groupBy('customer_id')
            ->orderByDesc('total_value')
            ->take(10)
            ->get();
        $crmCustNames = Customer::whereIn('id', $topCustDealStats->pluck('customer_id'))
            ->pluck('name', 'id');
        $topCustomers = $topCustDealStats->map(fn ($r) => [
            'customer_id' => $r->customer_id,
            'customer_name' => $crmCustNames[$r->customer_id] ?? null,
            'customer' => $r->customer_id ? [
                'id' => (int) $r->customer_id,
                'name' => $crmCustNames[$r->customer_id] ?? ('Cliente #'.$r->customer_id),
            ] : null,
            'total_value' => (float) $r->total_value,
            'deal_count' => (int) $r->deal_count,
        ]);

        // Calibrações vencendo (integração)
        $calibrationAlerts = Equipment::where('tenant_id', $tenantId)->calibrationDue(60)
            ->active()
            ->with('customer:id,name')
            ->orderBy('next_calibration_at')
            ->take(10)
            ->get(['id', 'code', 'brand', 'model', 'customer_id', 'next_calibration_at']);

        // Messaging stats (período selecionado)
        $msgInPeriod = CrmMessage::where('tenant_id', $tenantId)
            ->where('created_at', '>=', $start)->where('created_at', '<=', $end);
        $totalSent = (clone $msgInPeriod)->outbound()->count();
        $totalReceived = (clone $msgInPeriod)->inbound()->count();
        $whatsappSent = (clone $msgInPeriod)->outbound()->byChannel('whatsapp')->count();
        $emailSent = (clone $msgInPeriod)->outbound()->byChannel('email')->count();
        $delivered = (clone $msgInPeriod)->outbound()->whereIn('status', [CrmMessage::STATUS_DELIVERED, CrmMessage::STATUS_READ])->count();
        $failed = (clone $msgInPeriod)->outbound()->where('status', CrmMessage::STATUS_FAILED)->count();
        $deliveryRate = $totalSent > 0 ? round(($delivered / $totalSent) * 100, 1) : 0;

        $emailMessages = CrmMessage::where('tenant_id', $tenantId)
            ->where('channel', CrmMessage::CHANNEL_EMAIL)
            ->where('direction', CrmMessage::DIRECTION_OUTBOUND)
            ->where('created_at', '>=', $start)
            ->where('created_at', '<=', $end);
        $emailTotalSent = (clone $emailMessages)->count();
        $emailOpened = CrmTrackingEvent::where('tenant_id', $tenantId)
            ->where('event_type', 'email_opened')
            ->where('created_at', '>=', $start)
            ->where('created_at', '<=', $end)
            ->count();
        $emailClicked = CrmTrackingEvent::where('tenant_id', $tenantId)
            ->whereIn('event_type', ['email_clicked', 'link_clicked'])
            ->where('created_at', '>=', $start)
            ->where('created_at', '<=', $end)
            ->count();
        $emailReplied = (clone $emailMessages)
            ->whereExists(function ($query) {
                $query->selectRaw('1')
                    ->from('crm_messages as inbound_messages')
                    ->whereColumn('inbound_messages.customer_id', 'crm_messages.customer_id')
                    ->where('inbound_messages.channel', CrmMessage::CHANNEL_EMAIL)
                    ->where('inbound_messages.direction', CrmMessage::DIRECTION_INBOUND)
                    ->whereColumn('inbound_messages.created_at', '>=', 'crm_messages.created_at');
            })
            ->count();
        $emailBounced = (clone $emailMessages)
            ->where('status', CrmMessage::STATUS_FAILED)
            ->count();

        return ApiResponse::data([
            'period' => [
                'label' => $range['label'],
                'start' => $start->toIso8601String(),
                'end' => $end->toIso8601String(),
                'period' => $range['period'],
            ],
            'kpis' => [
                'open_deals' => $openDeals,
                'won_month' => $wonInPeriod,
                'lost_month' => $lostInPeriod,
                'revenue_in_pipeline' => round((float) $revenueInPipeline, 2),
                'won_revenue' => round((float) $wonRevenue, 2),
                'avg_health_score' => round($avgHealthScore),
                'no_contact_90d' => $noContact90,
                'conversion_rate' => $conversionRate,
            ],
            'previous_period' => [
                'won_month' => $prevWon,
                'lost_month' => $prevLost,
                'won_revenue' => round((float) $prevWonRevenue, 2),
            ],
            'messaging_stats' => [
                'sent_month' => $totalSent,
                'received_month' => $totalReceived,
                'whatsapp_sent' => $whatsappSent,
                'email_sent' => $emailSent,
                'delivered' => $delivered,
                'failed' => $failed,
                'delivery_rate' => $deliveryRate,
            ],
            'email_tracking' => [
                'total_sent' => $emailTotalSent,
                'opened' => $emailOpened,
                'clicked' => $emailClicked,
                'replied' => $emailReplied,
                'bounced' => $emailBounced,
            ],
            'pipelines' => $pipelines,
            'recent_deals' => $recentDeals,
            'upcoming_activities' => $upcomingActivities,
            'top_customers' => $topCustomers,
            'calibration_alerts' => $calibrationAlerts,
        ]);
    }

    public function pipelinesIndex(User $user, int $tenantId)
    {

        $pipelines = CrmPipeline::where('tenant_id', $tenantId)
            ->active()
            ->with(['stages' => function ($q) {
                $q->orderBy('sort_order');
            }])
            ->orderBy('sort_order')
            ->get();

        return ApiResponse::data(CrmPipelineResource::collection($pipelines));
    }

    public function pipelinesStore(array $data, User $user, int $tenantId)
    {

        DB::beginTransaction();

        try {
            $pipeline = CrmPipeline::create([
                'tenant_id' => $tenantId,
                'name' => $data['name'],
                'slug' => $data['slug'] ?? Str::slug($data['name']),
                'color' => $data['color'] ?? null,
            ]);

            foreach ($data['stages'] as $i => $stage) {
                $pipeline->stages()->create([
                    'tenant_id' => $tenantId,
                    'name' => $stage['name'],
                    'color' => $stage['color'] ?? null,
                    'sort_order' => $i,
                    'probability' => $stage['probability'] ?? 0,
                    'is_won' => $stage['is_won'] ?? false,
                    'is_lost' => $stage['is_lost'] ?? false,
                ]);
            }

            DB::commit();
            $pipeline->load('stages');

            return ApiResponse::data(new CrmPipelineResource($pipeline), 201, [
                'stages' => $pipeline->stages,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erro ao criar pipeline', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao criar pipeline', 500);
        }
    }

    public function pipelinesUpdate(array $data, CrmPipeline $pipeline, User $user, int $tenantId)
    {

        $pipeline->update($data);

        return ApiResponse::data(new CrmPipelineResource($pipeline->load('stages')));
    }

    public function pipelinesDestroy(CrmPipeline $pipeline, User $user, int $tenantId)
    {

        $dealCount = CrmDeal::where('pipeline_id', $pipeline->id)->count();
        if ($dealCount > 0) {
            return ApiResponse::message(
                "Não é possível excluir pipeline com {$dealCount} deal(s) vinculado(s). Mova ou exclua os deals primeiro.",
                422
            );
        }

        DB::beginTransaction();

        try {
            $pipeline->stages()->delete();
            $pipeline->delete();
            DB::commit();

            return ApiResponse::noContent();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erro ao excluir pipeline', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao excluir pipeline', 500);
        }
    }

    public function stagesStore(array $data, CrmPipeline $pipeline, User $user, int $tenantId)
    {

        $data['tenant_id'] = $tenantId;
        $maxOrder = $pipeline->stages()->max('sort_order') ?? -1;
        $data['sort_order'] = $maxOrder + 1;

        $stage = $pipeline->stages()->create($data);

        return ApiResponse::data($stage, 201);
    }

    public function stagesUpdate(array $data, CrmPipelineStage $stage, User $user, int $tenantId)
    {

        $stage->update($data);

        return ApiResponse::data($stage);
    }

    public function stagesDestroy(CrmPipelineStage $stage, User $user, int $tenantId)
    {

        $dealCount = $stage->deals()->count();
        if ($dealCount > 0) {
            return ApiResponse::message(
                "Não é possível excluir etapa com {$dealCount} deal(s). Mova os deals primeiro.",
                422
            );
        }

        try {
            DB::transaction(fn () => $stage->delete());

            return ApiResponse::noContent();
        } catch (\Exception $e) {
            Log::error('Erro ao excluir estágio', ['id' => $stage->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao excluir estágio', 500);
        }
    }

    public function stagesReorder(array $data, CrmPipeline $pipeline, User $user, int $tenantId)
    {

        foreach ($data['stage_ids'] as $i => $stageId) {
            CrmPipelineStage::where('id', $stageId)
                ->where('pipeline_id', $pipeline->id)
                ->update(['sort_order' => $i]);
        }

        return ApiResponse::data(new CrmPipelineResource($pipeline->fresh()->load('stages')));
    }

    public function dealsIndex(array $data, User $user, int $tenantId, bool $isScoped = false)
    {

        $query = CrmDeal::with([
            'customer:id,name',
            'stage:id,name,color,sort_order',
            'pipeline:id,name',
            'assignee:id,name',
        ])->where('tenant_id', $tenantId);

        if ($isScoped) {
            $query->where('assigned_to', $user->id);
        }

        if (isset($data['pipeline_id'])) {
            $query->byPipeline($data['pipeline_id']);
        }
        if (isset($data['status'])) {
            $query->where('status', $data['status']);
        }
        if (isset($data['assigned_to'])) {
            $query->where('assigned_to', $data['assigned_to']);
        }
        if (isset($data['customer_id'])) {
            $query->where('customer_id', $data['customer_id']);
        }

        $deals = $query->orderByDesc('updated_at')->paginate(min((int) ($data['per_page'] ?? 50), 100));

        return ApiResponse::paginated($deals, resourceClass: CrmDealResource::class);
    }

    public function dealsStore(array $data, User $user, int $tenantId)
    {
        return app(CreateDealAction::class)->execute($data, $user, $tenantId);
    }

    public function dealsShow(CrmDeal $deal, User $user, int $tenantId)
    {

        $deal->load([
            'customer:id,name,phone,email,health_score',
            'stage:id,name,color,probability',
            'pipeline:id,name',
            'assignee:id,name',
            'quote:id,quote_number,total,status',
            'workOrder:id,number,os_number,status,total',
            'equipment:id,code,brand,model',
            'activities' => fn ($q) => $q->with('user:id,name')->orderByDesc('created_at')->take(20),
        ]);

        return ApiResponse::data(new CrmDealResource($deal));
    }

    public function dealsUpdate(array $data, CrmDeal $deal, User $user, int $tenantId)
    {
        return app(UpdateDealAction::class)->execute($data, $deal, $user, $tenantId);
    }

    public function dealsUpdateStage(array $data, CrmDeal $deal, User $user, int $tenantId)
    {
        return app(UpdateDealStageAction::class)->execute($data, $deal, $user, $tenantId);
    }

    public function dealsMarkWon(array $data, CrmDeal $deal, User $user, int $tenantId)
    {
        return app(MarkDealAsWonAction::class)->execute($data, $deal, $user, $tenantId);
    }

    public function dealsMarkLost(array $data, CrmDeal $deal, User $user, int $tenantId)
    {
        return app(MarkDealAsLostAction::class)->execute($data, $deal, $user, $tenantId);
    }

    public function dealsConvertToWorkOrder(array $data, CrmDeal $deal, User $user, int $tenantId)
    {
        return app(ConvertDealToWorkOrderAction::class)->execute($data, $deal, $user, $tenantId);
    }

    public function dealsConvertToQuote(array $data, CrmDeal $deal, User $user, int $tenantId)
    {
        return app(ConvertDealToQuoteAction::class)->execute($deal, $user, $tenantId);
    }

    public function dealsDestroy(CrmDeal $deal, User $user, int $tenantId)
    {

        try {
            DB::transaction(function () use ($deal) {
                $deal->activities()->delete();
                $deal->delete();
            });

            return ApiResponse::noContent();
        } catch (\Exception $e) {
            Log::error('Erro ao excluir deal', ['id' => $deal->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao excluir deal', 500);
        }
    }

    public function dealsBulkUpdate(array $data, User $user, int $tenantId)
    {
        return app(BulkUpdateDealsAction::class)->execute($data, $user, $tenantId);
    }

    public function activitiesIndex(array $data, User $user, int $tenantId)
    {

        $query = CrmActivity::where('tenant_id', $tenantId)
            ->with(['customer:id,name', 'deal:id,title', 'user:id,name', 'contact:id,name']);

        if (isset($data['customer_id'])) {
            $query->where('customer_id', $data['customer_id']);
        }
        if (isset($data['contact_id'])) {
            $query->where('contact_id', $data['contact_id']);
        }
        if (isset($data['deal_id'])) {
            $query->where('deal_id', $data['deal_id']);
        }
        if (isset($data['type'])) {
            $query->byType($data['type']);
        }
        if (isset($data['pending'])) {
            $query->pending();
        }

        $activities = $query->orderByDesc('created_at')
            ->paginate(min((int) ($data['per_page'] ?? 30), 100));

        return ApiResponse::paginated($activities, resourceClass: CrmActivityResource::class);
    }

    public function activitiesStore(array $data, User $user, int $tenantId)
    {
        return app(CreateCrmActivityAction::class)->execute($data, $user, $tenantId);
    }

    public function activitiesUpdate(array $data, CrmActivity $activity, User $user, int $tenantId)
    {
        return app(UpdateCrmActivityAction::class)->execute($data, $activity, $user, $tenantId);
    }

    public function activitiesDestroy(CrmActivity $activity, User $user, int $tenantId)
    {
        return app(DeleteCrmActivityAction::class)->execute($activity, $user, $tenantId);
    }

    public function customer360(array $data, Customer $customer, User $user, int $tenantId)
    {
        return app(GetCustomer360DataAction::class)->execute($data, $customer, $user, $tenantId);
    }

    public function export360(array $data, $id, User $user, int $tenantId)
    {

        $customer = Customer::findOrFail($id);
        $res = $this->customer360($data, $customer, $user, $tenantId);

        if ($res->getStatusCode() !== 200) {
            return $res;
        }

        $content = $res->getData(true);
        $pdf = Pdf::loadView('pdfs.customer-360', $content)
            ->setPaper('a4', 'portrait');

        return $pdf->download("Universo_Cliente_{$id}.pdf");
    }

    public function constants(User $user, int $tenantId)
    {

        $sources = LeadSource::query()->active()->ordered()->get()->pluck('name', 'slug')->all();
        $segments = CustomerSegment::query()->active()->ordered()->get()->pluck('name', 'slug')->all();
        $contractTypes = ContractType::query()->active()->ordered()->get()->pluck('name', 'slug')->all();
        $companySizes = CustomerCompanySize::query()->active()->ordered()->get()->pluck('name', 'slug')->all();
        $customerRatings = CustomerRating::query()->active()->ordered()->get()->pluck('name', 'slug')->all();
        $quoteSources = QuoteSource::query()->active()->ordered()->get()->pluck('name', 'slug')->all();

        return ApiResponse::data([
            'deal_statuses' => CrmDeal::STATUSES,
            'deal_sources' => ! empty($quoteSources) ? $quoteSources : CrmDeal::SOURCES,
            'activity_types' => CrmActivity::TYPES,
            'activity_outcomes' => CrmActivity::OUTCOMES,
            'activity_channels' => CrmActivity::CHANNELS,
            'customer_sources' => ! empty($sources) ? $sources : Customer::SOURCES,
            'customer_segments' => ! empty($segments) ? $segments : Customer::SEGMENTS,
            'customer_sizes' => ! empty($companySizes) ? $companySizes : Customer::COMPANY_SIZES,
            'customer_contract_types' => ! empty($contractTypes) ? $contractTypes : Customer::CONTRACT_TYPES,
            'customer_ratings' => ! empty($customerRatings) ? $customerRatings : Customer::RATINGS,
        ]);
    }
}
