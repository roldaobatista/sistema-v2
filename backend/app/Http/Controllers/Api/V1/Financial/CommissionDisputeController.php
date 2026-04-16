<?php

namespace App\Http\Controllers\Api\V1\Financial;

use App\Enums\CommissionDisputeStatus;
use App\Enums\CommissionEventStatus;
use App\Enums\CommissionSettlementStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Financial\ResolveCommissionDisputeRequest;
use App\Http\Requests\Financial\StoreCommissionDisputeRequest;
use App\Models\CommissionDispute;
use App\Models\CommissionEvent;
use App\Models\CommissionSettlement;
use App\Models\Notification;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CommissionDisputeController extends Controller
{
    use ResolvesCurrentTenant;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CommissionDispute::class);

        $statusFilter = CommissionDisputeStatus::normalizeFilter($request->string('status')->toString());

        $query = CommissionDispute::with(['user:id,name', 'commissionEvent.workOrder:id,os_number,number,customer_id', 'resolver:id,name'])
            ->where('tenant_id', $this->tenantId())
            ->when($statusFilter, fn ($q, $statuses) => $q->whereIn('status', $statuses))
            ->when($request->get('user_id'), fn ($q, $userId) => $q->where('user_id', $userId))
            ->when($request->get('os_number'), function ($queryBuilder) use ($request) {
                $term = '%'.$request->get('os_number').'%';
                $queryBuilder->whereHas('commissionEvent.workOrder', fn ($workOrderQuery) => $workOrderQuery
                    ->where('os_number', 'like', $term)
                    ->orWhere('number', 'like', $term));
            })
            ->orderByDesc('created_at');

        return ApiResponse::paginated($query->paginate(min((int) $request->get('per_page', 50), 100)));
    }

    public function myIndex(Request $request): JsonResponse
    {
        $statusFilter = CommissionDisputeStatus::normalizeFilter($request->string('status')->toString());

        $query = CommissionDispute::with(['commissionEvent.workOrder:id,os_number,number,customer_id'])
            ->where('tenant_id', $this->tenantId())
            ->where('user_id', $request->user()->id)
            ->when($statusFilter, fn ($q, $statuses) => $q->whereIn('status', $statuses))
            ->when($request->get('os_number'), function ($queryBuilder) use ($request) {
                $term = '%'.$request->get('os_number').'%';
                $queryBuilder->whereHas('commissionEvent.workOrder', fn ($workOrderQuery) => $workOrderQuery
                    ->where('os_number', 'like', $term)
                    ->orWhere('number', 'like', $term));
            })
            ->orderByDesc('created_at');

        return ApiResponse::paginated($query->paginate(min((int) $request->get('per_page', 50), 100)));
    }

    public function store(StoreCommissionDisputeRequest $request): JsonResponse
    {
        $this->authorize('create', CommissionDispute::class);

        $validated = $request->validated();
        $tenantId = $this->tenantId();
        $user = $request->user();
        $event = CommissionEvent::with('settlement')
            ->where('tenant_id', $tenantId)
            ->findOrFail($validated['commission_event_id']);

        $canManageAnyDispute = $user->can('commissions.dispute.resolve');

        if ((int) $event->user_id !== (int) $user->id && ! $canManageAnyDispute) {
            return ApiResponse::message('Sem permissao para contestar eventos de outros usuarios.', 403);
        }

        try {
            $dispute = DB::transaction(function () use ($validated, $tenantId, $user, $event) {
                // Lock event para verificar status dentro da transação (TOCTOU)
                $lockedEvent = CommissionEvent::lockForUpdate()->find($event->id);
                if ($lockedEvent->status === CommissionEventStatus::PAID || $lockedEvent->settlement?->status === CommissionSettlementStatus::PAID) {
                    abort(422, 'Nao e permitido abrir contestacao para comissao ja paga.');
                }

                // Verificar duplicata com lock para evitar race condition
                $existing = CommissionDispute::where('tenant_id', $tenantId)
                    ->where('commission_event_id', $validated['commission_event_id'])
                    ->where('status', CommissionDisputeStatus::OPEN)
                    ->lockForUpdate()
                    ->exists();

                if ($existing) {
                    abort(422, 'já existe uma contestacao aberta para este evento.');
                }

                $dispute = CommissionDispute::create([
                    'tenant_id' => $tenantId,
                    'commission_event_id' => $validated['commission_event_id'],
                    'user_id' => $user->id,
                    'reason' => $validated['reason'],
                    'status' => CommissionDisputeStatus::OPEN,
                ]);

                Notification::notify(
                    $tenantId,
                    (int) ($event->user_id ?? $user->id),
                    'commission_dispute',
                    'Comissao contestada',
                    [
                        'message' => "Uma contestacao foi aberta: {$validated['reason']}",
                        'icon' => 'alert-circle',
                        'color' => 'warning',
                        'notifiable_type' => CommissionDispute::class,
                        'notifiable_id' => $dispute->id,
                        'data' => [
                            'dispute_id' => $dispute->id,
                            'commission_event_id' => $event->id,
                        ],
                    ]
                );

                return $dispute->load([
                    'user:id,name',
                    'commissionEvent.workOrder:id,os_number,number,customer_id',
                ]);
            });

            return ApiResponse::data($dispute, 201);
        } catch (HttpException $e) {
            return ApiResponse::message($e->getMessage(), $e->getStatusCode());
        } catch (\Throwable $e) {
            Log::error('Commission dispute store failed', [
                'tenant_id' => $tenantId,
                'user_id' => $user->id,
                'commission_event_id' => $validated['commission_event_id'],
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::message('Erro ao registrar contestacao.', 500);
        }
    }

    public function resolve(ResolveCommissionDisputeRequest $request, int $id): JsonResponse
    {
        $validated = $request->validated();

        $dispute = CommissionDispute::where('tenant_id', $this->tenantId())->find($id);

        if (! $dispute) {
            return ApiResponse::message('Contestacao nao encontrada.', 404);
        }

        $this->authorize('resolve', $dispute);

        try {
            DB::transaction(function () use ($dispute, $validated) {
                $locked = CommissionDispute::lockForUpdate()->find($dispute->id);

                if (! $locked->isOpen()) {
                    abort(422, 'Contestacao já resolvida.');
                }

                if (isset($validated['new_amount']) && $validated['status'] === CommissionDisputeStatus::ACCEPTED->value) {
                    $originalAmount = (string) $locked->commissionEvent->commission_amount;
                    if (bccomp((string) $validated['new_amount'], $originalAmount, 2) > 0) {
                        abort(422, 'O novo valor não pode exceder o valor original.');
                    }
                }

                $event = $locked->commissionEvent()->with('settlement')->first();
                if (! $event) {
                    abort(404, 'Evento de comissao nao encontrado.');
                }

                if (
                    $validated['status'] === CommissionDisputeStatus::ACCEPTED->value
                    && ($event->status === CommissionEventStatus::PAID || $event->settlement?->status === CommissionSettlementStatus::PAID)
                ) {
                    abort(422, 'Nao e permitido aceitar contestacao de comissao ja paga. Reabra o fechamento antes de ajustar.');
                }

                $locked->update([
                    'status' => $validated['status'],
                    'resolution_notes' => $validated['resolution_notes'],
                    'resolved_by' => auth()->id(),
                    'resolved_at' => now(),
                ]);

                if ($validated['status'] === CommissionDisputeStatus::ACCEPTED->value) {
                    $event = $locked->commissionEvent;
                    $suffix = " | Ajustado via contestacao #{$dispute->id}";

                    if (isset($validated['new_amount'])) {
                        $event->update([
                            'commission_amount' => $validated['new_amount'],
                            'notes' => ($event->notes ?? '').$suffix,
                        ]);
                    } else {
                        $event->update([
                            'status' => CommissionEventStatus::REVERSED,
                            'notes' => ($event->notes ?? '').$suffix,
                        ]);
                    }

                    if ($event->settlement_id) {
                        CommissionSettlement::find($event->settlement_id)?->recalculateTotals();
                    }
                }
            });
        } catch (HttpException $e) {
            return ApiResponse::message($e->getMessage(), $e->getStatusCode());
        } catch (\Throwable $e) {
            Log::error('Commission dispute resolve failed', [
                'tenant_id' => $this->tenantId(),
                'user_id' => auth()->id(),
                'dispute_id' => $dispute->id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::message('Erro ao resolver contestacao.', 500);
        }

        try {
            $statusLabel = $validated['status'] === CommissionDisputeStatus::ACCEPTED->value ? 'aceita' : 'rejeitada';
            Notification::notify(
                $this->tenantId(),
                (int) $dispute->user_id,
                'commission_dispute_resolved',
                'Contestacao '.ucfirst($statusLabel),
                [
                    'message' => "Sua contestacao foi {$statusLabel}: {$validated['resolution_notes']}",
                    'icon' => $validated['status'] === CommissionDisputeStatus::ACCEPTED->value ? 'check-circle' : 'x-circle',
                    'color' => $validated['status'] === CommissionDisputeStatus::ACCEPTED->value ? 'success' : 'danger',
                    'notifiable_type' => CommissionDispute::class,
                    'notifiable_id' => $dispute->id,
                    'data' => [
                        'dispute_id' => $dispute->id,
                        'commission_event_id' => $dispute->commission_event_id,
                        'status' => $validated['status'],
                    ],
                ]
            );
        } catch (\Throwable $e) {
            Log::warning('Commission dispute notification failed', [
                'dispute_id' => $dispute->id,
                'error' => $e->getMessage(),
            ]);
        }

        return ApiResponse::data($dispute->fresh()->load([
            'user:id,name',
            'commissionEvent.workOrder:id,os_number,number,customer_id',
            'resolver:id,name',
        ]));
    }

    public function show(int $id): JsonResponse
    {
        $dispute = CommissionDispute::with([
            'user:id,name',
            'commissionEvent.workOrder:id,os_number,number,customer_id',
            'resolver:id,name',
        ])->where('tenant_id', $this->tenantId())->find($id);

        if (! $dispute) {
            return ApiResponse::message('Contestacao nao encontrada.', 404);
        }

        $this->authorize('view', $dispute);

        return ApiResponse::data($dispute);
    }

    public function destroy(int $id): JsonResponse
    {
        $dispute = CommissionDispute::where('tenant_id', $this->tenantId())->find($id);

        if (! $dispute) {
            return ApiResponse::message('Contestacao nao encontrada.', 404);
        }

        $this->authorize('delete', $dispute);

        $userId = auth()->id();

        try {
            DB::transaction(function () use ($dispute) {
                $locked = CommissionDispute::lockForUpdate()->find($dispute->id);
                if (! $locked || ! $locked->isOpen()) {
                    abort(422, 'Apenas contestacoes abertas podem ser canceladas.');
                }
                $locked->delete();
            });

            return ApiResponse::noContent();
        } catch (HttpException $e) {
            return ApiResponse::message($e->getMessage(), $e->getStatusCode());
        } catch (\Throwable $e) {
            Log::error('Commission dispute destroy failed', [
                'tenant_id' => $this->tenantId(),
                'user_id' => $userId,
                'dispute_id' => $dispute->id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::message('Erro ao cancelar contestacao.', 500);
        }
    }
}
