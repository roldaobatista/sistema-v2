<?php

namespace App\Http\Controllers\Api\V1\Crm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\ImportDealsCsvRequest;
use App\Http\Requests\Crm\StoreCrmCalendarEventRequest;
use App\Http\Requests\Crm\StoreCrmReferralRequest;
use App\Http\Requests\Crm\UpdateCrmCalendarEventRequest;
use App\Http\Requests\Crm\UpdateCrmReferralRequest;
use App\Models\CrmActivity;
use App\Models\CrmCalendarEvent;
use App\Models\CrmContractRenewal;
use App\Models\CrmDeal;
use App\Models\CrmDealCompetitor;
use App\Models\CrmForecastSnapshot;
use App\Models\CrmInteractiveProposal;
use App\Models\CrmLeadScore;
use App\Models\CrmLeadScoringRule;
use App\Models\CrmLossReason;
use App\Models\CrmPipeline;
use App\Models\CrmReferral;
use App\Models\CrmSalesGoal;
use App\Models\CrmSequence;
use App\Models\CrmSequenceEnrollment;
use App\Models\CrmSequenceStep;
use App\Models\CrmSmartAlert;
use App\Models\CrmTerritoryMember;
use App\Models\CrmTrackingEvent;
use App\Models\Customer;
use App\Support\ApiResponse;
use App\Support\SearchSanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CrmEngagementController extends Controller
{
    private function tenantId(Request $request): int
    {
        $user = $request->user();

        return (int) ($user->current_tenant_id ?? $user->tenant_id);
    }

    private function mapActivityToCalendarEvent(CrmActivity $activity): array
    {
        $startAt = $activity->scheduled_at instanceof Carbon
            ? $activity->scheduled_at->copy()
            : Carbon::parse($activity->scheduled_at);

        return [
            'id' => 'activity-'.$activity->id,
            'title' => $activity->title,
            'description' => $activity->description,
            'start_at' => $startAt->toIso8601String(),
            'end_at' => $startAt->copy()->addMinutes($activity->duration_minutes ?? 30)->toIso8601String(),
            'type' => match ($activity->type) {
                CrmActivity::TYPE_MEETING => 'meeting', CrmActivity::TYPE_CALL => 'call',
                CrmActivity::TYPE_VISIT => 'visit', CrmActivity::TYPE_TASK => 'follow_up',
                default => 'activity',
            },
            'customer' => $activity->customer, 'deal' => $activity->deal, 'user' => $activity->user,
            'all_day' => false, 'is_activity' => true, 'completed' => $activity->completed_at !== null,
        ];
    }

    // ── Referral Program ──────────────────────────────────

    public function referrals(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('crm.referral.view'), 403);

        return ApiResponse::paginated(
            CrmReferral::where('tenant_id', $this->tenantId($request))
                ->with(['referrer:id,name', 'referred:id,name', 'deal:id,title,status,value'])
                ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
                ->orderByDesc('created_at')
                ->paginate(min((int) $request->input('per_page', 20), 100))
        );
    }

    public function referralOptions(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('crm.deal.view'), 403);

        $tenantId = $this->tenantId($request);
        $customers = Customer::where('tenant_id', $tenantId)->where('is_active', true)->select('id', 'name', 'document')->orderBy('name')->limit(250)->get();
        $deals = CrmDeal::where('tenant_id', $tenantId)->with('customer:id,name')
            ->select('id', 'title', 'value', 'status', 'customer_id')->orderByDesc('updated_at')->limit(250)->get()
            ->map(fn (CrmDeal $deal) => [
                'id' => (int) $deal->id, 'title' => $deal->title, 'status' => $deal->status,
                'value' => (float) ($deal->value ?? 0), 'customer_id' => $deal->customer_id, 'customer_name' => $deal->customer?->name,
            ]);

        return ApiResponse::data(['customers' => $customers, 'deals' => $deals]);
    }

    public function storeReferral(StoreCrmReferralRequest $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        $data = $request->validated();
        $referral = CrmReferral::create([...$data, 'tenant_id' => $tenantId]);

        return ApiResponse::data($referral->load(['referrer:id,name', 'referred:id,name', 'deal:id,title,status,value']), 201);
    }

    public function updateReferral(UpdateCrmReferralRequest $request, CrmReferral $referral): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        if ((int) $referral->tenant_id !== $tenantId) {
            return ApiResponse::message('Registro de indicacao não encontrado', 404);
        }
        $data = $request->validated();
        $referral->update($data);
        if (($data['status'] ?? null) === 'converted') {
            $referral->update(['converted_at' => now()]);
        }
        if ($data['reward_given'] ?? false) {
            $referral->update(['reward_given_at' => now()]);
        }

        return ApiResponse::data($referral->fresh()->load(['referrer:id,name', 'referred:id,name', 'deal:id,title,status,value']));
    }

    public function destroyReferral(Request $request, CrmReferral $referral): JsonResponse
    {
        abort_unless($request->user()->can('crm.deal.delete'), 403);

        $tenantId = $this->tenantId($request);
        if ((int) $referral->tenant_id !== $tenantId) {
            return ApiResponse::message('Registro de indicacao não encontrado', 404);
        }

        try {
            $referral->delete();

            return ApiResponse::message('Indicacao removida com sucesso');
        } catch (\Exception $e) {
            Log::error('CrmEngagement destroyReferral failed', ['referral_id' => $referral->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao remover indicacao', 500);
        }
    }

    public function referralStats(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('crm.deal.view'), 403);

        $tenantId = $this->tenantId($request);
        $totalRewardValue = (float) CrmReferral::where('tenant_id', $tenantId)->where('reward_given', true)->sum('reward_value');

        $topReferrers = CrmReferral::where('tenant_id', $tenantId)
            ->select('referrer_customer_id', DB::raw('COUNT(*) as count'), DB::raw("SUM(CASE WHEN status = 'converted' THEN 1 ELSE 0 END) as converted_count"))
            ->groupBy('referrer_customer_id')->with('referrer:id,name')->orderByDesc('count')->limit(10)->get()
            ->map(fn (CrmReferral $referral) => [
                'id' => (int) $referral->referrer_customer_id,
                'name' => $referral->referrer?->name ?? ('Cliente #'.$referral->referrer_customer_id),
                'count' => (int) ($referral->count ?? 0), 'converted_count' => (int) ($referral->converted_count ?? 0),
            ])->values();

        $stats = [
            'total' => CrmReferral::where('tenant_id', $tenantId)->count(),
            'pending' => CrmReferral::where('tenant_id', $tenantId)->where('status', 'pending')->count(),
            'converted' => CrmReferral::where('tenant_id', $tenantId)->where('status', 'converted')->count(),
            'conversion_rate' => 0, 'total_reward_value' => $totalRewardValue, 'total_rewards' => $totalRewardValue,
            'top_referrers' => $topReferrers,
        ];
        if ($stats['total'] > 0) {
            $stats['conversion_rate'] = round(($stats['converted'] / $stats['total']) * 100, 1);
        }

        return ApiResponse::data($stats, 200, $stats);
    }

    // ── Commercial Calendar ───────────────────────────────

    public function calendarEvents(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('crm.deal.view'), 403);

        $tenantId = $this->tenantId($request);
        $start = $request->input('start', now()->startOfMonth()->toDateString());
        $end = $request->input('end', now()->endOfMonth()->toDateString());

        $events = CrmCalendarEvent::where('tenant_id', $tenantId)
            ->with(['customer:id,name', 'deal:id,title', 'user:id,name'])
            ->between($start, $end)
            ->when($request->input('user_id'), fn ($q, $uid) => $q->byUser($uid))
            ->orderBy('start_at')->get();

        $activities = CrmActivity::where('tenant_id', $tenantId)->whereNotNull('scheduled_at')
            ->whereBetween('scheduled_at', [$start, $end])
            ->when($request->input('user_id'), fn ($q, $uid) => $q->where('user_id', $uid))
            ->with(['customer:id,name', 'deal:id,title', 'user:id,name'])->get()
            ->map(fn (CrmActivity $activity) => $this->mapActivityToCalendarEvent($activity));

        $renewals = CrmContractRenewal::where('tenant_id', $tenantId)
            ->whereBetween('contract_end_date', [$start, $end])->with('customer:id,name')->get()
            ->map(fn ($r) => [
                'id' => 'renewal-'.$r->id, 'title' => 'Venc. Contrato: '.($r->customer->name ?? ''),
                'start_at' => $r->contract_end_date, 'end_at' => $r->contract_end_date,
                'type' => 'contract_renewal', 'customer' => $r->customer, 'all_day' => true, 'is_renewal' => true,
            ]);

        return ApiResponse::data(['events' => $events, 'activities' => $activities, 'renewals' => $renewals]);
    }

    public function storeCalendarEvent(StoreCrmCalendarEventRequest $request): JsonResponse
    {
        $data = $request->validated();
        $event = CrmCalendarEvent::create([...$data, 'tenant_id' => $this->tenantId($request), 'user_id' => $request->user()->id]);

        return ApiResponse::data($event, 201);
    }

    public function updateCalendarEvent(UpdateCrmCalendarEventRequest $request, CrmCalendarEvent $event): JsonResponse
    {
        $event->update($request->validated());

        return ApiResponse::data($event);
    }

    public function destroyCalendarEvent(Request $request, CrmCalendarEvent $event): JsonResponse
    {
        abort_unless($request->user()->can('crm.deal.delete'), 403);

        try {
            $event->delete();

            return ApiResponse::message('Evento removido');
        } catch (\Exception $e) {
            Log::error('CrmEngagement destroyCalendarEvent failed', ['event_id' => $event->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao remover evento', 500);
        }
    }

    // ── Calendar Activities Integration ───────────────────

    public function calendarActivities(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('crm.deal.view'), 403);

        $tenantId = $this->tenantId($request);
        $start = $request->input('start', now()->startOfMonth()->toDateString());
        $end = $request->input('end', now()->endOfMonth()->toDateString());

        $activities = CrmActivity::where('tenant_id', $tenantId)->whereNotNull('scheduled_at')
            ->whereBetween('scheduled_at', [$start, $end])
            ->when($request->input('user_id'), fn ($q, $uid) => $q->where('user_id', $uid))
            ->with(['customer:id,name', 'deal:id,title', 'user:id,name'])->orderBy('scheduled_at')->get()
            ->map(fn (CrmActivity $activity) => $this->mapActivityToCalendarEvent($activity));

        return ApiResponse::data($activities);
    }

    // ── Constants ─────────────────────────────────────────

    public function featuresConstants(): JsonResponse
    {
        return ApiResponse::data([
            'scoring_categories' => CrmLeadScoringRule::CATEGORIES, 'scoring_operators' => CrmLeadScoringRule::OPERATORS,
            'lead_grades' => CrmLeadScore::GRADES, 'sequence_statuses' => CrmSequence::STATUSES,
            'sequence_action_types' => CrmSequenceStep::ACTION_TYPES, 'enrollment_statuses' => CrmSequenceEnrollment::STATUSES,
            'alert_types' => CrmSmartAlert::TYPES, 'alert_priorities' => CrmSmartAlert::PRIORITIES,
            'loss_reason_categories' => CrmLossReason::CATEGORIES, 'competitor_outcomes' => CrmDealCompetitor::OUTCOMES,
            'territory_roles' => CrmTerritoryMember::ROLES, 'goal_period_types' => CrmSalesGoal::PERIOD_TYPES,
            'renewal_statuses' => CrmContractRenewal::STATUSES, 'proposal_statuses' => CrmInteractiveProposal::STATUSES,
            'tracking_event_types' => CrmTrackingEvent::EVENT_TYPES, 'referral_statuses' => CrmReferral::STATUSES,
            'referral_reward_types' => CrmReferral::REWARD_TYPES, 'calendar_event_types' => CrmCalendarEvent::TYPES,
            'forecast_period_types' => CrmForecastSnapshot::PERIOD_TYPES,
        ]);
    }

    // ── CSV Export / Import ───────────────────────────────

    public function exportDealsCsv(Request $request): mixed
    {
        abort_unless($request->user()->can('crm.deal.view'), 403);

        $tenantId = $this->tenantId($request);
        $query = CrmDeal::where('tenant_id', $tenantId)->with(['customer:id,name', 'pipeline:id,name', 'stage:id,name', 'assignee:id,name']);
        if ($request->input('pipeline_id')) {
            $query->where('pipeline_id', (int) $request->input('pipeline_id'));
        }
        if ($request->input('status')) {
            $query->where('status', $request->input('status'));
        }
        $deals = $query->orderByDesc('created_at')->get();

        $headers = ['ID', 'Título', 'Cliente', 'Pipeline', 'Etapa', 'Valor', 'Probabilidade', 'Status', 'Origem', 'Responsável', 'Previsão Fechamento', 'Criado em'];
        $callback = function () use ($deals, $headers) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($file, $headers, ';');
            foreach ($deals as $deal) {
                $status = $deal->status;
                fputcsv($file, [
                    $deal->id, $deal->title, $deal->customer?->name ?? '', $deal->pipeline?->name ?? '',
                    $deal->stage?->name ?? '', number_format($deal->value ?? 0, 2, ',', '.'),
                    $deal->probability ?? 0, $status, $deal->source ?? '', $deal->assignee?->name ?? '',
                    $deal->expected_close_date?->format('d/m/Y') ?? '', $deal->created_at->format('d/m/Y H:i'),
                ], ';');
            }
            fclose($file);
        };

        $filename = 'deals_export_'.now()->format('Y-m-d_His').'.csv';

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function importDealsCsv(ImportDealsCsvRequest $request): JsonResponse
    {
        $request->validated();
        $tenantId = $this->tenantId($request);
        $file = $request->file('file');
        $imported = 0;
        $errors = [];

        $handle = fopen($file->getPathname(), 'r');
        $headerRow = fgetcsv($handle, 0, ';');
        if (! $headerRow) {
            return ApiResponse::data(['imported' => 0, 'errors' => ['Arquivo CSV vazio ou malformado']], 422);
        }

        $headerMap = array_flip(array_map('mb_strtolower', array_map('trim', $headerRow)));
        $row = 1;

        $defaultPipeline = CrmPipeline::where('tenant_id', $tenantId)->default()->first();
        $firstStage = $defaultPipeline?->stages()->orderBy('sort_order')->first();

        while (($data = fgetcsv($handle, 0, ';')) !== false) {
            $row++;

            try {
                $title = trim($data[$headerMap['título'] ?? $headerMap['titulo'] ?? $headerMap['title'] ?? 0] ?? '');
                if (! $title) {
                    $errors[] = "Linha {$row}: título obrigatório";
                    continue;
                }

                $customerName = trim($data[$headerMap['cliente'] ?? $headerMap['customer'] ?? 99] ?? '');
                $customer = null;
                if ($customerName) {
                    $safeCustomerName = SearchSanitizer::contains($customerName);
                    $customer = Customer::where('tenant_id', $tenantId)->where('name', 'like', $safeCustomerName)->first();
                }
                if (! $customer) {
                    $errors[] = "Linha {$row}: cliente '{$customerName}' não encontrado";
                    continue;
                }

                $valueStr = trim($data[$headerMap['valor'] ?? $headerMap['value'] ?? 99] ?? '0');
                $value = (float) str_replace(['.', ','], ['', '.'], $valueStr);

                CrmDeal::create([
                    'tenant_id' => $tenantId, 'title' => $title, 'customer_id' => $customer->id,
                    'pipeline_id' => $defaultPipeline?->id, 'stage_id' => $firstStage?->id, 'value' => $value,
                    'source' => trim($data[$headerMap['origem'] ?? $headerMap['source'] ?? 99] ?? '') ?: null,
                    'assigned_to' => $request->user()->id,
                ]);
                $imported++;
            } catch (\Throwable $e) {
                $errors[] = "Linha {$row}: ".$e->getMessage();
            }
        }
        fclose($handle);

        return ApiResponse::data(['imported' => $imported, 'errors' => array_slice($errors, 0, 20)]);
    }
}
