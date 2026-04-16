<?php

namespace App\Http\Controllers\Api\V1\Financial;

use App\Enums\CommissionEventStatus;
use App\Enums\CommissionSettlementStatus;
use App\Enums\FinancialStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Financial\BulkUpdateCommissionEventStatusRequest;
use App\Http\Requests\Financial\CloseCommissionPeriodRequest;
use App\Http\Requests\Financial\CommissionBalanceSummaryRequest;
use App\Http\Requests\Financial\CommissionBatchGenerateRequest;
use App\Http\Requests\Financial\CommissionStatementRequest;
use App\Http\Requests\Financial\CommissionWorkOrderRequest;
use App\Http\Requests\Financial\MarkSettlementPaidRequest;
use App\Http\Requests\Financial\MyStatementPeriodRequest;
use App\Http\Requests\Financial\RejectSettlementRequest;
use App\Http\Requests\Financial\SplitCommissionEventRequest;
use App\Http\Requests\Financial\UpdateCommissionEventStatusRequest;
use App\Models\AccountPayable;
use App\Models\CommissionEvent;
use App\Models\CommissionGoal;
use App\Models\CommissionRule;
use App\Models\CommissionSettlement;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\CommissionService;
use App\Support\ApiResponse;
use App\Support\Decimal;
use App\Support\SearchSanitizer;
use App\Traits\ResolvesCurrentTenant;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CommissionController extends Controller
{
    use ResolvesCurrentTenant;

    protected CommissionService $commissionService;

    public function __construct(CommissionService $commissionService)
    {
        $this->commissionService = $commissionService;
    }

    /** Cross-DB period filter (SQLite + MySQL compatible) */
    private function wherePeriod($query, string $column, string $period)
    {
        $allowed = ['created_at', 'updated_at', 'completed_at', 'received_at', 'paid_at', 'due_date', 'event_date'];
        if (! in_array($column, $allowed, true)) {
            throw new \InvalidArgumentException("Column '{$column}' not allowed in wherePeriod");
        }

        if (DB::getDriverName() === 'sqlite') {
            $query->whereRaw("strftime('%Y-%m', {$column}) = ?", [$period]);
        } else {
            $query->whereRaw("DATE_FORMAT({$column}, '%Y-%m') = ?", [$period]);
        }

        return $query;
    }

    private function applyEffectiveCommissionPeriodFilter($query, string $period): void
    {
        $periodStart = Carbon::createFromFormat('!Y-m', $period)->startOfMonth()->startOfDay();
        $periodEnd = (clone $periodStart)->endOfMonth()->endOfDay();

        $query->where(function ($outerQuery) use ($periodStart, $periodEnd): void {
            $outerQuery
                ->whereHas('workOrder', function ($workOrderQuery) use ($periodStart, $periodEnd): void {
                    $workOrderQuery->where(function ($candidateQuery) use ($periodStart, $periodEnd): void {
                        $candidateQuery
                            ->whereBetween('completed_at', [$periodStart, $periodEnd])
                            ->orWhere(function ($fallbackQuery) use ($periodStart, $periodEnd): void {
                                $fallbackQuery
                                    ->whereNull('completed_at')
                                    ->whereBetween('received_at', [$periodStart, $periodEnd]);
                            });
                    });
                })
                ->orWhere(function ($fallbackWithoutWorkOrderQuery) use ($periodStart, $periodEnd): void {
                    $fallbackWithoutWorkOrderQuery
                        ->whereDoesntHave('workOrder')
                        ->whereBetween('created_at', [$periodStart, $periodEnd]);
                })
                ->orWhere(function ($fallbackWithoutOperationalDateQuery) use ($periodStart, $periodEnd): void {
                    $fallbackWithoutOperationalDateQuery
                        ->whereBetween('created_at', [$periodStart, $periodEnd])
                        ->whereHas('workOrder', function ($workOrderQuery): void {
                            $workOrderQuery
                                ->whereNull('completed_at')
                                ->whereNull('received_at');
                        });
                });
        });
    }

    private function osNumberFilter(Request $request): ?string
    {
        $osNumber = trim((string) $request->get('os_number', ''));

        return $osNumber !== '' ? $osNumber : null;
    }

    private function applyWorkOrderIdentifierFilter($query, ?string $osNumber): void
    {
        if (! $osNumber) {
            return;
        }

        $safe = SearchSanitizer::contains($osNumber);
        $query->whereHas('workOrder', function ($wo) use ($safe) {
            $wo->where(function ($q) use ($safe) {
                $q->where('os_number', 'like', $safe)
                    ->orWhere('number', 'like', $safe);
            });
        });
    }

    private function eligibleSettlementEventsQuery(int $tenantId, int $userId, string $period, ?int $settlementId = null)
    {
        $query = CommissionEvent::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('status', CommissionEventStatus::APPROVED)
            ->where(function ($settlementQuery) use ($settlementId): void {
                $settlementQuery->whereNull('settlement_id');

                if ($settlementId !== null) {
                    $settlementQuery->orWhere('settlement_id', $settlementId);
                }
            });

        $this->applyEffectiveCommissionPeriodFilter($query, $period);

        return $query;
    }

    private function syncSettlementEvents(CommissionSettlement $settlement): array
    {
        $events = $this->eligibleSettlementEventsQuery(
            (int) $settlement->tenant_id,
            (int) $settlement->user_id,
            (string) $settlement->period,
            (int) $settlement->id
        )
            ->get();

        $eventIds = $events->pluck('id');
        $linkedEventsQuery = CommissionEvent::query()
            ->where('tenant_id', $settlement->tenant_id)
            ->where('settlement_id', $settlement->id);

        if ($eventIds->isEmpty()) {
            $linkedEventsQuery->update(['settlement_id' => null]);
        } else {
            $linkedEventsQuery
                ->whereNotIn('id', $eventIds)
                ->update(['settlement_id' => null]);

            CommissionEvent::whereIn('id', $eventIds)->update(['settlement_id' => $settlement->id]);
        }

        return [
            'events' => $events,
            'total_amount' => $events->sum('commission_amount'),
            'events_count' => $events->count(),
        ];
    }

    private function refreshSettlementSnapshot(?int $settlementId): void
    {
        if (! $settlementId) {
            return;
        }

        $settlement = CommissionSettlement::query()
            ->where('tenant_id', $this->tenantId())
            ->find($settlementId);

        if (! $settlement) {
            return;
        }

        CommissionEvent::query()
            ->where('tenant_id', $settlement->tenant_id)
            ->where('settlement_id', $settlement->id)
            ->whereNotIn('status', [CommissionEventStatus::APPROVED, CommissionEventStatus::PAID])
            ->update(['settlement_id' => null]);

        $totals = CommissionEvent::query()
            ->where('tenant_id', $settlement->tenant_id)
            ->where('settlement_id', $settlement->id)
            ->whereIn('status', [CommissionEventStatus::APPROVED, CommissionEventStatus::PAID])
            ->selectRaw('COALESCE(SUM(commission_amount), 0) as total_amount, COUNT(*) as events_count')
            ->first();

        $settlement->update([
            'total_amount' => $totals?->total_amount ?? 0,
            'events_count' => (int) ($totals?->events_count ?? 0),
        ]);
    }

    // ── Eventos ──

    public function events(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CommissionEvent::class);

        $osNumber = $this->osNumberFilter($request);
        $query = CommissionEvent::where('tenant_id', $this->tenantId())
            ->with(['user:id,name', 'workOrder:id,number,os_number,customer_id', 'rule:id,name,calculation_type']);

        if ($userId = $request->get('user_id')) {
            $query->where('user_id', $userId);
        }
        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }
        if ($period = $request->get('period')) {
            $this->applyEffectiveCommissionPeriodFilter($query, $period);
        }
        $this->applyWorkOrderIdentifierFilter($query, $osNumber);

        return ApiResponse::paginated(
            $query->orderByDesc('created_at')->paginate(min((int) $request->get('per_page', 50), 100))
        );
    }

    /** Gerar comissões para uma OS — suporta 10+ calculation_types */
    public function generateForWorkOrder(CommissionWorkOrderRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $wo = WorkOrder::where('tenant_id', $this->tenantId())->findOrFail($validated['work_order_id']);

        try {
            $events = $this->commissionService->calculateAndGenerate($wo);

            // Notification is now handled here or could be moved to service events,
            // but keeping it here for now to match previous behavior (controller orchestrates notifications)
            $this->notifyCommissionGenerated($events);

            return ApiResponse::data(['generated' => count($events), 'events' => $events], 201);

        } catch (HttpException $e) {
            return ApiResponse::message($e->getMessage(), $e->getStatusCode());
        } catch (\DomainException|\InvalidArgumentException $e) {
            return ApiResponse::message($e->getMessage(), 422);
        } catch (\Exception $e) {
            Log::error('Commission generation failed', ['wo_id' => $wo->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao gerar comissões.', 500);
        }
    }

    /** Simular comissão (preview sem salvar) */
    public function simulate(CommissionWorkOrderRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $wo = WorkOrder::where('tenant_id', $this->tenantId())->findOrFail($validated['work_order_id']);

        $simulations = $this->commissionService->simulate($wo);

        return ApiResponse::data($simulations);
    }

    public function updateEventStatus(UpdateCommissionEventStatusRequest $request, CommissionEvent $commissionEvent): JsonResponse
    {
        $this->authorize('update', $commissionEvent);

        abort_if((int) $commissionEvent->tenant_id !== $this->tenantId(), 404);

        $validated = $request->validated();

        $oldStatus = $commissionEvent->status;
        $newStatus = CommissionEventStatus::from($validated['status']);

        if (! $oldStatus->canTransitionTo($newStatus)) {
            return ApiResponse::message("Transição de status inválida: {$oldStatus->value} → {$newStatus->value}", 422);
        }

        try {
            DB::transaction(function () use ($commissionEvent, $validated, $oldStatus, $newStatus) {
                $commissionEvent = CommissionEvent::lockForUpdate()->findOrFail($commissionEvent->id);

                if (! $commissionEvent->status->canTransitionTo($newStatus)) {
                    throw new \DomainException("Transição de status inválida: {$commissionEvent->status->value} → {$newStatus->value}");
                }

                $settlementId = $commissionEvent->settlement_id ? (int) $commissionEvent->settlement_id : null;

                $commissionEvent->update($validated);
                $this->refreshSettlementSnapshot($settlementId);

                if (in_array($newStatus, [CommissionEventStatus::APPROVED, CommissionEventStatus::PAID])) {
                    $this->notifyStatusChange($commissionEvent, $oldStatus->value, $newStatus->value);
                }
            });

            return ApiResponse::data($commissionEvent->fresh());
        } catch (\DomainException $e) {
            return ApiResponse::message($e->getMessage(), 422);
        } catch (\Exception $e) {
            Log::error('Falha ao atualizar status do evento de comissão', ['error' => $e->getMessage(), 'event_id' => $commissionEvent->id]);

            return ApiResponse::message('Erro interno ao atualizar status', 500);
        }
    }

    /** Aprovação/Estorno em lote */
    public function batchUpdateStatus(BulkUpdateCommissionEventStatusRequest $request): JsonResponse
    {
        $this->authorize('updateAny', CommissionEvent::class);

        $tenantId = $this->tenantId();
        $validated = $request->validated();

        try {
            $updated = 0;
            $skipped = 0;

            DB::transaction(function () use ($tenantId, $validated, &$updated, &$skipped) {
                $events = CommissionEvent::where('tenant_id', $tenantId)
                    ->whereIn('id', $validated['ids'])
                    ->lockForUpdate()
                    ->get();
                $settlementIds = [];

                foreach ($events as $event) {
                    $targetStatus = CommissionEventStatus::from($validated['status']);
                    if (! $event->status->canTransitionTo($targetStatus)) {
                        $skipped++;
                        continue;
                    }
                    if ($event->settlement_id) {
                        $settlementIds[(int) $event->settlement_id] = true;
                    }
                    $oldStatus = $event->status->value;
                    $event->update(['status' => $targetStatus]);
                    $updated++;

                    if (in_array($targetStatus, [CommissionEventStatus::APPROVED, CommissionEventStatus::PAID])) {
                        $this->notifyStatusChange($event, $oldStatus, $targetStatus->value);
                    }
                }

                foreach (array_keys($settlementIds) as $settlementId) {
                    $this->refreshSettlementSnapshot((int) $settlementId);
                }
            });

            $message = "{$updated} eventos atualizados";
            if ($skipped > 0) {
                $message .= ", {$skipped} ignorados (transição inválida)";
            }

            return ApiResponse::data(['updated' => $updated, 'skipped' => $skipped], 200, [
                'meta' => ['message' => $message],
            ]);
        } catch (\Exception $e) {
            Log::error('Falha no batch update de comissões', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro interno ao atualizar eventos em lote', 500);
        }
    }

    // ── Splits ──

    public function eventSplits(CommissionEvent $commissionEvent): JsonResponse
    {
        $this->authorize('view', $commissionEvent);

        abort_if((int) $commissionEvent->tenant_id !== $this->tenantId(), 404);

        $splits = DB::table('commission_splits')
            ->where('commission_event_id', $commissionEvent->id)
            ->where('commission_splits.tenant_id', $this->tenantId())
            ->join('users', 'commission_splits.user_id', '=', 'users.id')
            ->select('commission_splits.*', 'users.name as user_name')
            ->get();

        return ApiResponse::data($splits);
    }

    public function splitEvent(SplitCommissionEventRequest $request, CommissionEvent $commissionEvent): JsonResponse
    {
        $this->authorize('update', $commissionEvent);

        $tenantId = $this->tenantId();
        abort_if((int) $commissionEvent->tenant_id !== $tenantId, 404);

        $validated = $request->validated();

        $totalPct = collect($validated['splits'])->reduce(
            fn (string $carry, array $s) => bcadd($carry, (string) $s['percentage'], 2),
            '0.00'
        );
        if (bccomp($totalPct, '100.00', 2) !== 0) {
            return ApiResponse::message('A soma das porcentagens deve ser exatamente 100%', 422);
        }

        $baseAmount = Decimal::string($commissionEvent->commission_amount);

        $splits = DB::transaction(function () use ($commissionEvent, $validated, $tenantId, $baseAmount) {
            // Delete existing splits
            DB::table('commission_splits')->where('commission_event_id', $commissionEvent->id)->where('tenant_id', $tenantId)->delete();

            $splits = [];
            foreach ($validated['splits'] as $s) {
                $amount = bcmul($baseAmount, bcdiv(Decimal::string($s['percentage']), '100', 6), 2);
                $splitId = DB::table('commission_splits')->insertGetId([
                    'tenant_id' => $tenantId,
                    'commission_event_id' => $commissionEvent->id,
                    'user_id' => $s['user_id'],
                    'percentage' => $s['percentage'],
                    'amount' => $amount,
                    'role' => $s['role'] ?? CommissionRule::ROLE_TECHNICIAN,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $splits[] = ['id' => $splitId, 'user_id' => $s['user_id'], 'percentage' => $s['percentage'], 'amount' => $amount];
            }

            return $splits;
        });

        return ApiResponse::data($splits);
    }

    // ── Fechamento ──

    public function settlements(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CommissionSettlement::class);

        $statusFilter = CommissionSettlementStatus::normalizeFilter($request->string('status')->toString());

        $query = CommissionSettlement::where('tenant_id', $this->tenantId())
            ->with(['user:id,name', 'closer:id,name', 'approver:id,name']);

        if ($period = $request->get('period')) {
            $query->where('period', $period);
        }
        if ($userId = $request->get('user_id')) {
            $query->where('user_id', $userId);
        }
        if ($statusFilter) {
            $query->whereIn('status', $statusFilter);
        }

        return ApiResponse::paginated($query->orderByDesc('period')->paginate(min((int) $request->get('per_page', 50), 100)));
    }

    public function closeSettlement(CloseCommissionPeriodRequest $request): JsonResponse
    {
        $this->authorize('create', CommissionSettlement::class);

        $tenantId = $this->tenantId();
        $validated = $request->validated();

        if ($validated['period'] > now()->format('Y-m')) {
            return ApiResponse::message('Nao e permitido fechar periodos futuros', 422);
        }

        $settlement = CommissionSettlement::where('tenant_id', $tenantId)
            ->where('user_id', $validated['user_id'])
            ->where('period', $validated['period'])
            ->first();

        $events = $this->eligibleSettlementEventsQuery(
            $tenantId,
            (int) $validated['user_id'],
            (string) $validated['period'],
            $settlement?->id
        )->get();

        if ($events->isEmpty()) {
            return ApiResponse::message('Nenhum evento aprovado para este periodo', 422);
        }

        $settlement = DB::transaction(function () use ($tenantId, $validated, $settlement) {
            $currentSettlement = $settlement
                ? CommissionSettlement::query()
                    ->where('tenant_id', $tenantId)
                    ->whereKey($settlement->id)
                    ->lockForUpdate()
                    ->firstOrFail()
                : new CommissionSettlement([
                    'tenant_id' => $tenantId,
                    'user_id' => $validated['user_id'],
                    'period' => $validated['period'],
                ]);

            if ($currentSettlement->exists && $currentSettlement->status === CommissionSettlementStatus::PAID) {
                abort(422, 'Periodo ja pago e nao pode ser reaberto');
            }

            $currentSettlement->fill([
                'total_amount' => 0,
                'events_count' => 0,
                'status' => CommissionSettlementStatus::CLOSED,
                'closed_by' => auth()->id(),
                'closed_at' => now(),
                'paid_at' => null,
                'paid_amount' => 0,
                'payment_notes' => null,
                'approved_by' => null,
                'approved_at' => null,
                'rejection_reason' => null,
            ]);
            $currentSettlement->save();

            $sync = $this->syncSettlementEvents($currentSettlement);
            $currentSettlement->update([
                'total_amount' => $sync['total_amount'],
                'events_count' => $sync['events_count'],
            ]);

            return $currentSettlement;
        });

        return ApiResponse::data($settlement->load('user:id,name'), 201);
    }

    public function paySettlement(MarkSettlementPaidRequest $request, CommissionSettlement $commissionSettlement): JsonResponse
    {
        $this->authorize('update', $commissionSettlement);

        $tenantId = $this->tenantId();
        if ((int) $commissionSettlement->tenant_id !== $tenantId) {
            return ApiResponse::message('Registro não encontrado.', 404);
        }

        $validated = $request->validated();

        try {
            return DB::transaction(function () use ($commissionSettlement, $validated, $tenantId, $request) {
                $commissionSettlement = CommissionSettlement::query()
                    ->where('tenant_id', $tenantId)
                    ->whereKey($commissionSettlement->id)
                    ->with('user:id,name')
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($commissionSettlement->status === CommissionSettlementStatus::PAID) {
                    return ApiResponse::message('Fechamento já está pago', 422);
                }

                $canonicalStatus = CommissionSettlementStatus::canonicalValue($commissionSettlement->status);

                if (! in_array($canonicalStatus, [CommissionSettlementStatus::CLOSED->value, CommissionSettlementStatus::APPROVED->value], true)) {
                    return ApiResponse::message('Somente fechamentos com status FECHADO ou APROVADO podem ser pagos', 422);
                }

                $paidAmount = $validated['paid_amount'] ?? $commissionSettlement->total_amount;

                if (bccomp((string) $paidAmount, '0', 2) <= 0) {
                    return ApiResponse::message('O valor pago deve ser maior que zero', 422);
                }

                if (bccomp(Decimal::string($paidAmount), Decimal::string($commissionSettlement->total_amount), 2) !== 0) {
                    return ApiResponse::message('Pagamento parcial de fechamento de comissao nao e suportado', 422);
                }

                $commissionSettlement->update([
                    'status' => CommissionSettlementStatus::PAID,
                    'paid_at' => now(),
                    'paid_amount' => $paidAmount,
                    'payment_notes' => $validated['payment_notes'] ?? null,
                ]);

                $this->notifySettlementStatusChange($commissionSettlement, 'PAID');

                CommissionEvent::where('settlement_id', $commissionSettlement->id)
                    ->where('status', CommissionEventStatus::APPROVED)
                    ->update(['status' => CommissionEventStatus::PAID]);

                // Gera AccountPayable para registrar a obrigação contábil
                AccountPayable::updateOrCreate([
                    'tenant_id' => $tenantId,
                    'notes' => $this->commissionSettlementPayableNote($commissionSettlement->id),
                ], [
                    'tenant_id' => $tenantId,
                    'created_by' => $request->user()->id,
                    'description' => "Comissão {$commissionSettlement->period} — {$commissionSettlement->user->name}",
                    'amount' => $paidAmount,
                    'amount_paid' => $paidAmount,
                    'due_date' => now()->toDateString(),
                    'paid_at' => now(),
                    'status' => FinancialStatus::PAID->value,
                    'payment_method' => $validated['payment_method'] ?? 'transfer',
                    'notes' => $this->commissionSettlementPayableNote($commissionSettlement->id),
                ]);

                return ApiResponse::data($commissionSettlement->fresh()->load('user:id,name'));
            });
        } catch (\Exception $e) {
            Log::error('Falha ao pagar fechamento', ['error' => $e->getMessage(), 'settlement_id' => $commissionSettlement->id]);

            return ApiResponse::message('Erro interno ao pagar fechamento', 500);
        }
    }

    private function commissionSettlementPayableNote(int $settlementId): string
    {
        return "commission_settlement:{$settlementId}";
    }

    public function reopenSettlement(CommissionSettlement $commissionSettlement): JsonResponse
    {
        $this->authorize('update', $commissionSettlement);

        $tenantId = $this->tenantId();
        if ((int) $commissionSettlement->tenant_id !== $tenantId) {
            return ApiResponse::message('Registro não encontrado.', 404);
        }

        if ($commissionSettlement->status === CommissionSettlementStatus::PAID) {
            return ApiResponse::message('Fechamento já foi pago e não pode ser reaberto', 422);
        }

        if (! in_array(CommissionSettlementStatus::canonicalValue($commissionSettlement->status), [
            CommissionSettlementStatus::CLOSED->value,
            CommissionSettlementStatus::APPROVED->value,
            CommissionSettlementStatus::REJECTED->value,
        ], true)) {
            return ApiResponse::message('Somente fechamentos fechados, aprovados ou rejeitados podem ser reabertos', 422);
        }

        try {
            DB::transaction(function () use ($commissionSettlement) {
                $commissionSettlement = CommissionSettlement::lockForUpdate()->findOrFail($commissionSettlement->id);

                if ($commissionSettlement->status === CommissionSettlementStatus::PAID) {
                    throw new \DomainException('Fechamento já foi pago e não pode ser reaberto');
                }

                if (! in_array(CommissionSettlementStatus::canonicalValue($commissionSettlement->status), [
                    CommissionSettlementStatus::CLOSED->value,
                    CommissionSettlementStatus::APPROVED->value,
                    CommissionSettlementStatus::REJECTED->value,
                ], true)) {
                    throw new \DomainException('Somente fechamentos fechados, aprovados ou rejeitados podem ser reabertos');
                }

                $commissionSettlement->update([
                    'status' => CommissionSettlementStatus::OPEN,
                    'paid_at' => null,
                    'paid_amount' => 0,
                    'payment_notes' => null,
                    'closed_by' => null,
                    'closed_at' => null,
                    'approved_by' => null,
                    'approved_at' => null,
                    'rejection_reason' => null,
                ]);

                // Reverter eventos vinculados a este settlement — apenas APPROVED volta para PENDING
                // Eventos PAID não podem ser revertidos (já foram liquidados)
                $paidCount = CommissionEvent::where('settlement_id', $commissionSettlement->id)
                    ->where('status', CommissionEventStatus::PAID)
                    ->count();

                if ($paidCount > 0) {
                    throw new \DomainException("Existem {$paidCount} eventos já pagos neste fechamento. Não é possível reabrir.");
                }

                CommissionEvent::where('settlement_id', $commissionSettlement->id)
                    ->where('status', CommissionEventStatus::APPROVED)
                    ->update(['status' => CommissionEventStatus::PENDING, 'settlement_id' => null]);
            });
        } catch (\DomainException $e) {
            return ApiResponse::message($e->getMessage(), 422);
        } catch (\Exception $e) {
            Log::error('Falha ao reabrir fechamento', ['error' => $e->getMessage(), 'settlement_id' => $commissionSettlement->id]);

            return ApiResponse::message('Erro interno ao reabrir fechamento', 500);
        }

        return ApiResponse::data($commissionSettlement->fresh()->load('user:id,name'));
    }

    /**
     * GAP-25: Aprovar settlement (workflow: Nayara fecha → Roldão aprova → pode pagar).
     */
    public function approveSettlement(Request $request, CommissionSettlement $commissionSettlement): JsonResponse
    {
        $this->authorize('approve', $commissionSettlement);

        $tenantId = $this->tenantId();
        if ((int) $commissionSettlement->tenant_id !== $tenantId) {
            return ApiResponse::message('Registro não encontrado.', 404);
        }

        if (CommissionSettlementStatus::canonicalValue($commissionSettlement->status) !== CommissionSettlementStatus::CLOSED->value) {
            return ApiResponse::message('Somente fechamentos com status "fechado" podem ser aprovados', 422);
        }

        try {
            DB::transaction(function () use ($commissionSettlement, $request) {
                $commissionSettlement = CommissionSettlement::lockForUpdate()->findOrFail($commissionSettlement->id);

                if (CommissionSettlementStatus::canonicalValue($commissionSettlement->status) !== CommissionSettlementStatus::CLOSED->value) {
                    throw new \DomainException('Somente fechamentos com status "fechado" podem ser aprovados');
                }

                $commissionSettlement->update([
                    'status' => CommissionSettlementStatus::APPROVED,
                    'approved_by' => $request->user()->id,
                    'approved_at' => now(),
                ]);

                $this->notifySettlementStatusChange($commissionSettlement, 'APPROVED');
            });

            return ApiResponse::data($commissionSettlement->fresh()->load('user:id,name'));
        } catch (\DomainException $e) {
            return ApiResponse::message($e->getMessage(), 422);
        } catch (\Exception $e) {
            Log::error('Falha ao aprovar settlement', ['error' => $e->getMessage(), 'id' => $commissionSettlement->id]);

            return ApiResponse::message('Erro interno ao aprovar fechamento', 500);
        }
    }

    /**
     * GAP-25: Rejeitar settlement (volta para "open" com motivo).
     */
    public function rejectSettlement(RejectSettlementRequest $request, CommissionSettlement $commissionSettlement): JsonResponse
    {
        $this->authorize('approve', $commissionSettlement);

        $tenantId = $this->tenantId();
        if ((int) $commissionSettlement->tenant_id !== $tenantId) {
            return ApiResponse::message('Registro não encontrado.', 404);
        }

        if (CommissionSettlementStatus::canonicalValue($commissionSettlement->status) !== CommissionSettlementStatus::CLOSED->value) {
            return ApiResponse::message('Somente fechamentos com status "fechado" podem ser rejeitados', 422);
        }

        $validated = $request->validated();

        try {
            DB::transaction(function () use ($commissionSettlement, $validated) {
                $commissionSettlement = CommissionSettlement::lockForUpdate()->findOrFail($commissionSettlement->id);

                if (CommissionSettlementStatus::canonicalValue($commissionSettlement->status) !== CommissionSettlementStatus::CLOSED->value) {
                    throw new \DomainException('Somente fechamentos com status "fechado" podem ser rejeitados');
                }

                $commissionSettlement->update([
                    'status' => CommissionSettlementStatus::REJECTED,
                    'rejection_reason' => $validated['rejection_reason'],
                    'approved_by' => null,
                    'approved_at' => null,
                    'paid_at' => null,
                    'paid_amount' => 0,
                    'payment_notes' => null,
                ]);

                // Desvincular eventos do settlement rejeitado, revertendo para pendente
                CommissionEvent::where('settlement_id', $commissionSettlement->id)
                    ->whereIn('status', [CommissionEventStatus::APPROVED, CommissionEventStatus::PAID])
                    ->update(['status' => CommissionEventStatus::PENDING, 'settlement_id' => null]);
            });

            return ApiResponse::data($commissionSettlement->fresh()->load('user:id,name'));
        } catch (\DomainException $e) {
            return ApiResponse::message($e->getMessage(), 422);
        } catch (\Exception $e) {
            Log::error('Falha ao rejeitar settlement', ['error' => $e->getMessage(), 'id' => $commissionSettlement->id]);

            return ApiResponse::message('Erro interno ao rejeitar fechamento', 500);
        }
    }

    // ── Export ──

    public function exportEvents(Request $request): StreamedResponse|JsonResponse
    {
        $this->authorize('viewAny', CommissionEvent::class);

        try {
            $tenantId = $this->tenantId();
            $osNumber = $this->osNumberFilter($request);
            $query = CommissionEvent::where('tenant_id', $tenantId)
                ->with(['user:id,name', 'workOrder:id,number,os_number,customer_id', 'rule:id,name,calculation_type']);

            if ($userId = $request->get('user_id')) {
                $query->where('user_id', $userId);
            }
            if ($status = $request->get('status')) {
                $query->where('status', $status);
            }
            if ($period = $request->get('period')) {
                $this->applyEffectiveCommissionPeriodFilter($query, $period);
            }
            $this->applyWorkOrderIdentifierFilter($query, $osNumber);

            $events = $query->orderByDesc('created_at')->get();

            return response()->streamDownload(function () use ($events) {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['Nome', 'OS', 'Regra', 'Tipo Cálculo', 'Valor Base', 'Comissão', 'Status', 'Data']);
                foreach ($events as $e) {
                    $status = $e->status instanceof CommissionEventStatus ? $e->status->value : $e->status;
                    fputcsv($out, [
                        $e->user?->name, $e->workOrder?->os_number ?? $e->workOrder?->number, $e->rule?->name,
                        $e->rule?->calculation_type, $e->base_amount, $e->commission_amount,
                        $status, $e->created_at?->format('Y-m-d'),
                    ]);
                }
                fclose($out);
            }, 'comissoes_eventos_'.now()->format('Y-m-d').'.csv', [
                'Content-Type' => 'text/csv',
            ]);
        } catch (\Exception $e) {
            Log::error('Falha ao exportar eventos de comissão', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao exportar eventos', 500);
        }
    }

    public function exportSettlements(Request $request): StreamedResponse|JsonResponse
    {
        try {
            $query = CommissionSettlement::where('tenant_id', $this->tenantId())
                ->with('user:id,name');

            if ($period = $request->get('period')) {
                $query->where('period', $period);
            }

            if ($userId = $request->get('user_id')) {
                $query->where('user_id', $userId);
            }

            $settlements = $query->orderByDesc('period')->get();

            return response()->streamDownload(function () use ($settlements) {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['Nome', 'Período', 'Qtd Eventos', 'Total', 'Status', 'Pago Em']);
                foreach ($settlements as $s) {
                    $status = $s->status instanceof CommissionSettlementStatus ? $s->status->value : $s->status;
                    fputcsv($out, [
                        $s->user?->name, $s->period, $s->events_count,
                        $s->total_amount, $status, $s->paid_at?->format('Y-m-d') ?? '',
                    ]);
                }
                fclose($out);
            }, 'comissoes_fechamento_'.now()->format('Y-m-d').'.csv', [
                'Content-Type' => 'text/csv',
            ]);
        } catch (\Exception $e) {
            Log::error('Falha ao exportar fechamentos de comissão', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao exportar fechamentos', 500);
        }
    }

    public function downloadStatement(CommissionStatementRequest $request): Response
    {
        $tenantId = $this->tenantId();
        $validated = $request->validated();

        $settlement = CommissionSettlement::where('tenant_id', $tenantId)
            ->where('user_id', $validated['user_id'])
            ->where('period', $validated['period'])
            ->first();

        $query = CommissionEvent::where('tenant_id', $tenantId)
            ->where('user_id', $validated['user_id'])
            ->with(['workOrder:id,number,os_number,customer_id', 'rule:id,name,calculation_type'])
            ->orderBy('created_at');
        $this->applyEffectiveCommissionPeriodFilter($query, $validated['period']);

        $events = $query->get();
        if ($events->isEmpty()) {
            return ApiResponse::message('Nenhum evento encontrado para este periodo.', 404);
        }

        $user = User::find($validated['user_id']);
        $total = (float) bcadd((string) ($settlement?->total_amount ?? $events->sum('commission_amount')), '0', 2);
        $html = view('pdf.commission-statement', [
            'userName' => $user?->name ?? "Usuário {$validated['user_id']}",
            'period' => $validated['period'],
            'generatedAt' => now(),
            'events' => $events,
            'totalAmount' => $total,
            'eventsCount' => $events->count(),
            'settlementStatus' => $settlement?->status,
            'paidAt' => $settlement?->paid_at,
        ])->render();

        $pdf = Pdf::loadHTML($html)->setPaper('A4', 'portrait');

        return $pdf->download("comissao-extrato-{$validated['period']}-{$validated['user_id']}.pdf");
    }

    public function myStatementDownload(MyStatementPeriodRequest $request): Response
    {
        $tenantId = $this->tenantId();
        $userId = auth()->id();
        $validated = $request->validated();
        $period = $validated['period'];

        $settlement = CommissionSettlement::where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('period', $period)
            ->first();

        $query = CommissionEvent::where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->with(['workOrder:id,number,os_number,customer_id', 'rule:id,name,calculation_type'])
            ->orderBy('created_at');
        $this->applyEffectiveCommissionPeriodFilter($query, $period);

        $events = $query->get();
        if ($events->isEmpty()) {
            return ApiResponse::message('Nenhum evento encontrado para este periodo.', 404);
        }

        $total = (float) bcadd((string) ($settlement?->total_amount ?? $events->sum('commission_amount')), '0', 2);
        $html = view('pdf.commission-statement', [
            'userName' => auth()->user()->name,
            'period' => $period,
            'generatedAt' => now(),
            'events' => $events,
            'totalAmount' => $total,
            'eventsCount' => $events->count(),
            'settlementStatus' => $settlement?->status,
            'paidAt' => $settlement?->paid_at,
        ])->render();

        $pdf = Pdf::loadHTML($html)->setPaper('A4', 'portrait');

        return $pdf->download("meu-holerite-comissao-{$period}.pdf");
    }

    // ── Summary ──

    public function summary(): JsonResponse
    {
        $tenantId = $this->tenantId();

        $pendingTotal = bcadd((string) CommissionEvent::where('tenant_id', $tenantId)->where('status', CommissionEventStatus::PENDING)->sum('commission_amount'), '0', 2);
        $approvedTotal = bcadd((string) CommissionEvent::where('tenant_id', $tenantId)->where('status', CommissionEventStatus::APPROVED)->sum('commission_amount'), '0', 2);
        $paidMonth = bcadd((string) CommissionEvent::where('tenant_id', $tenantId)->where('status', CommissionEventStatus::PAID)
            ->whereMonth('updated_at', now()->month)
            ->whereYear('updated_at', now()->year)
            ->sum('commission_amount'), '0', 2);

        return ApiResponse::data([
            'pending' => $pendingTotal,
            'approved' => $approvedTotal,
            'paid_this_month' => $paidMonth,
            'calculation_types_count' => count(CommissionRule::CALCULATION_TYPES),
        ]);
    }

    // ── Helpers: Notifications ──

    private function notifyCommissionGenerated(array $events): void
    {
        try {
            // Eager load workOrder para evitar N+1 queries
            $eventIds = collect($events)->pluck('id')->filter();
            $eventsCollection = CommissionEvent::with('workOrder:id,number,os_number')
                ->whereIn('id', $eventIds)->get()->keyBy('id');

            // Batch load users to avoid N+1
            $userIds = collect($events)->pluck('user_id')->unique()->filter();
            $users = User::whereIn('id', $userIds)->get()->keyBy('id');

            $rows = [];
            $now = now();
            foreach ($events as $event) {
                $loaded = $eventsCollection->get($event->id) ?? $event;
                $user = $users->get($event->user_id);
                if (! $user) {
                    continue;
                }
                $rows[] = [
                    'id' => Str::uuid(),
                    'type' => 'App\\Notifications\\CommissionGenerated',
                    'notifiable_type' => 'App\\Models\\User',
                    'notifiable_id' => $user->id,
                    'data' => json_encode([
                        'title' => 'Nova Comissão Gerada',
                        'message' => 'Comissão de R$ '.number_format((float) $event->commission_amount, 2, ',', '.').' gerada para a OS #'.($loaded->workOrder?->os_number ?? $loaded->workOrder?->number ?? $event->work_order_id),
                        'type' => 'commission',
                        'event_id' => $event->id,
                    ]),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if (! empty($rows)) {
                // Batch insert for performance — chunks of 100 to avoid packet size limits
                foreach (array_chunk($rows, 100) as $chunk) {
                    DB::table('notifications')->insert($chunk);
                }
            }
        } catch (\Throwable) {
            // Notifications are non-critical
        }
    }

    private function notifyStatusChange(object $event, string $oldStatus, string $newStatus): void
    {
        try {
            $statusLabel = CommissionEventStatus::tryFrom($newStatus)?->label() ?? $newStatus;
            $amount = $event->commission_amount ?? 0;

            DB::table('notifications')->insert([
                'id' => Str::uuid(),
                'type' => 'App\\Notifications\\CommissionStatusChanged',
                'notifiable_type' => 'App\\Models\\User',
                'notifiable_id' => $event->user_id,
                'data' => json_encode([
                    'title' => 'Comissão '.ucfirst(mb_strtolower($statusLabel)),
                    'message' => 'Sua comissão de R$ '.number_format($amount, 2, ',', '.').' foi '.mb_strtolower($statusLabel).'.',
                    'type' => 'commission',
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable) {
            // Notifications are non-critical
        }
    }

    private function notifySettlementStatusChange(CommissionSettlement $settlement, string $status): void
    {
        try {
            DB::table('notifications')->insert([
                'id' => Str::uuid(),
                'type' => 'App\\Notifications\\CommissionSettlementChanged',
                'notifiable_type' => 'App\\Models\\User',
                'notifiable_id' => $settlement->user_id,
                'data' => json_encode([
                    'title' => 'Fechamento de Comissão '.($status === 'APPROVED' ? 'Aprovado' : 'Pago'),
                    'message' => 'Seu fechamento ref. a '.$settlement->period.' foi '.mb_strtolower($status === 'APPROVED' ? 'Aprovado' : 'Pago').' no valor de R$ '.number_format((float) ($status === 'PAID' ? $settlement->paid_amount : $settlement->total_amount), 2, ',', '.').'.',
                    'type' => 'commission',
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable) {
            // Notifications are non-critical
        }
    }

    // ── Batch Generate ──

    public function batchGenerateForWorkOrders(CommissionBatchGenerateRequest $request): JsonResponse
    {
        $tenantId = $this->tenantId();

        $validated = $request->validated();

        $query = WorkOrder::where('tenant_id', $tenantId)
            ->where(function ($q) use ($validated) {
                $q->where(function ($q2) use ($validated) {
                    $q2->whereDate('completed_at', '>=', $validated['date_from'])
                        ->whereDate('completed_at', '<=', $validated['date_to']);
                })->orWhere(function ($q2) use ($validated) {
                    $q2->whereNull('completed_at')
                        ->whereDate('received_at', '>=', $validated['date_from'])
                        ->whereDate('received_at', '<=', $validated['date_to']);
                });
            })
            ->whereNotIn('status', [WorkOrder::STATUS_CANCELLED])
            ->where(function ($q) {
                $q->where('total', '>', 0)->where(function ($q2) {
                    $q2->whereNull('is_warranty')->orWhere('is_warranty', false);
                });
            });

        if (! empty($validated['user_id'])) {
            $userId = $validated['user_id'];
            $query->where(function ($q) use ($userId) {
                $q->where('assigned_to', $userId)
                    ->orWhereHas('technicians', fn ($t) => $t->where('user_id', $userId));
            });
        }

        $workOrders = $query->get();

        $generated = 0;
        $skipped = 0;
        $errors = [];

        foreach ($workOrders as $wo) {
            try {
                $events = $this->commissionService->calculateAndGenerateAnyTrigger($wo);
                if (count($events) > 0) {
                    $generated++;
                    $this->notifyCommissionGenerated($events);
                } else {
                    $skipped++;
                }
            } catch (\Exception $e) {
                if (str_contains($e->getMessage(), 'já geradas')) {
                    $skipped++;
                } else {
                    $errors[] = "OS #{$wo->business_number}: {$e->getMessage()}";
                }
            }
        }

        $message = "{$generated} comissões geradas, {$skipped} ignoradas (já existiam ou sem regra)";
        if (count($errors) > 0) {
            $message .= '. '.count($errors).' erro(s)';
        }

        return ApiResponse::data([
            'total_orders' => $workOrders->count(),
            'generated' => $generated,
            'skipped' => $skipped,
            'errors' => $errors,
        ], 200, [
            'meta' => ['message' => $message],
        ]);
    }

    // ── Balance Summary ──

    public function balanceSummary(CommissionBalanceSummaryRequest $request): JsonResponse
    {
        $tenantId = $this->tenantId();

        $validated = $request->validated();

        $settlements = CommissionSettlement::where('tenant_id', $tenantId)
            ->where('user_id', $validated['user_id'])
            ->orderBy('period')
            ->get();

        $totalEarned = '0';
        $totalPaid = '0';
        $details = [];

        foreach ($settlements as $s) {
            $earned = (string) ($s->total_amount ?? '0');
            $paid = (string) ($s->paid_amount ?? '0');
            $totalEarned = bcadd($totalEarned, $earned, 2);
            $totalPaid = bcadd($totalPaid, $paid, 2);

            $details[] = [
                'id' => $s->id,
                'period' => $s->period,
                'total_amount' => bcadd($earned, '0', 2),
                'paid_amount' => bcadd($paid, '0', 2),
                'balance' => bcsub($earned, $paid, 2),
                'status' => $s->status,
                'paid_at' => $s->paid_at?->format('Y-m-d'),
                'payment_notes' => $s->payment_notes,
            ];
        }

        $pendingEvents = CommissionEvent::where('tenant_id', $tenantId)
            ->where('user_id', $validated['user_id'])
            ->whereNull('settlement_id')
            ->whereIn('status', [CommissionEventStatus::PENDING, CommissionEventStatus::APPROVED])
            ->sum('commission_amount');

        return ApiResponse::data([
            'total_earned' => bcadd($totalEarned, '0', 2),
            'total_paid' => bcadd($totalPaid, '0', 2),
            'balance' => bcsub($totalEarned, $totalPaid, 2),
            'pending_unsettled' => (float) $pendingEvents,
            'settlements' => $details,
        ]);
    }

    // ── My Commissions (para técnicos/vendedores verem suas próprias) ──

    public function myEvents(Request $request): JsonResponse
    {
        $userId = auth()->id();
        $query = CommissionEvent::where('tenant_id', $this->tenantId())
            ->where('user_id', $userId)
            ->with(['workOrder:id,number,os_number', 'rule:id,name,calculation_type']);

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }
        if ($period = $request->get('period')) {
            $this->applyEffectiveCommissionPeriodFilter($query, $period);
        }

        return ApiResponse::paginated($query->orderByDesc('created_at')->paginate(min((int) $request->get('per_page', 50), 100)));
    }

    public function mySettlements(Request $request): JsonResponse
    {
        $userId = auth()->id();
        $query = CommissionSettlement::where('tenant_id', $this->tenantId())
            ->where('user_id', $userId)
            ->with(['closer:id,name', 'approver:id,name']);

        if ($period = $request->get('period')) {
            $query->where('period', $period);
        }

        return ApiResponse::paginated($query->orderByDesc('period')->paginate(min((int) $request->get('per_page', 50), 100)));
    }

    public function mySummary(Request $request): JsonResponse
    {
        $userId = auth()->id();
        $tenantId = $this->tenantId();
        $period = $request->get('period');
        $includeAllPeriods = filter_var($request->get('all'), FILTER_VALIDATE_BOOLEAN);

        if (! $includeAllPeriods && (! is_string($period) || ! preg_match('/^\d{4}-\d{2}$/', $period))) {
            $period = now()->format('Y-m');
        }

        $events = CommissionEvent::where('tenant_id', $tenantId)
            ->where('user_id', $userId);

        $periodEvents = (clone $events);
        if (! $includeAllPeriods && is_string($period) && $period !== '') {
            $this->applyEffectiveCommissionPeriodFilter($periodEvents, $period);
        }

        $totalMonth = (clone $periodEvents)
            ->whereIn('status', [
                CommissionEventStatus::PENDING,
                CommissionEventStatus::APPROVED,
                CommissionEventStatus::PAID,
            ])
            ->sum('commission_amount');
        $pending = (clone $periodEvents)->whereIn('status', [CommissionEventStatus::PENDING, CommissionEventStatus::APPROVED])->sum('commission_amount');
        $paid = (clone $periodEvents)->where('status', CommissionEventStatus::PAID)->sum('commission_amount');

        $goal = null;
        if (! $includeAllPeriods && is_string($period) && $period !== '') {
            $goal = CommissionGoal::where('tenant_id', $tenantId)
                ->where('user_id', $userId)
                ->where('period', $period)
                ->first();
        }

        return ApiResponse::data([
            'total_month' => (float) $totalMonth,
            'pending' => (float) $pending,
            'paid' => (float) $paid,
            'goal' => $goal ? [
                'target_amount' => (float) $goal->target_amount,
                'achieved_amount' => (float) $goal->achieved_amount,
                'achievement_pct' => (float) $goal->progress_percentage,
                'type' => $goal->type,
            ] : null,
        ]);
    }
}
