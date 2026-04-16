<?php

namespace App\Http\Controllers\Api\V1\Os;

use App\Http\Controllers\Controller;
use App\Http\Requests\Os\RequestWorkOrderApprovalRequest;
use App\Http\Requests\Os\RespondWorkOrderApprovalRequest;
use App\Models\WorkOrder;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class WorkOrderApprovalController extends Controller
{
    use ResolvesCurrentTenant;

    private const APPROVAL_ELIGIBLE_STATUSES = [
        WorkOrder::STATUS_OPEN,
        WorkOrder::STATUS_COMPLETED,
    ];

    private function ensureTenantScope(Request $request, WorkOrder $workOrder): ?JsonResponse
    {
        return $this->ensureTenantOwnership($workOrder, 'OS Aprovação');
    }

    private function userBelongsToTenant(int $userId, int $tenantId): bool
    {
        return DB::table('users')
            ->where('id', $userId)
            ->where(function ($query) use ($tenantId) {
                $query
                    ->where('tenant_id', $tenantId)
                    ->orWhere('current_tenant_id', $tenantId);

                if (Schema::hasTable('user_tenants')) {
                    $query->orWhereExists(function ($subQuery) use ($tenantId) {
                        $subQuery
                            ->selectRaw('1')
                            ->from('user_tenants')
                            ->whereColumn('user_tenants.user_id', 'users.id')
                            ->where('user_tenants.tenant_id', $tenantId);
                    });
                }
            })
            ->exists();
    }

    public function index(Request $request, WorkOrder $workOrder): JsonResponse
    {
        $this->authorize('view', $workOrder);

        if ($response = $this->ensureTenantScope($request, $workOrder)) {
            return $response;
        }

        $approvals = DB::table('work_order_approvals')
            ->where('work_order_id', $workOrder->id)
            ->leftJoin('users', 'work_order_approvals.approver_id', '=', 'users.id')
            ->select('work_order_approvals.*', 'users.name as approver_name')
            ->orderByDesc('work_order_approvals.created_at')
            ->paginate(min((int) $request->input('per_page', 25), 100));

        return ApiResponse::paginated($approvals);
    }

    public function request(RequestWorkOrderApprovalRequest $request, WorkOrder $workOrder): JsonResponse
    {
        $this->authorize('update', $workOrder);

        if ($response = $this->ensureTenantScope($request, $workOrder)) {
            return $response;
        }

        $validated = $request->validated();

        $tenantId = $this->tenantId();
        $approverIds = collect($validated['approver_ids'])->map(fn ($id) => (int) $id)->unique()->values();

        foreach ($approverIds as $approverId) {
            if (! $this->userBelongsToTenant($approverId, $tenantId)) {
                return ApiResponse::message('Um ou mais aprovadores não pertencem ao tenant atual.', 422);
            }
        }

        $hasPendingApproval = DB::table('work_order_approvals')
            ->where('work_order_id', $workOrder->id)
            ->where('status', 'pending')
            ->exists();

        if ($hasPendingApproval) {
            return ApiResponse::message('Já existe aprovação pendente para esta OS.', 422);
        }

        if (! in_array($workOrder->status, self::APPROVAL_ELIGIBLE_STATUSES, true)) {
            return ApiResponse::message('A OS precisa estar aberta ou concluída para solicitar aprovação.', 422);
        }

        try {
            $approvals = DB::transaction(function () use ($approverIds, $validated, $workOrder, $request) {
                $created = [];
                $fromStatus = $workOrder->status;

                foreach ($approverIds as $approverId) {
                    $created[] = DB::table('work_order_approvals')->insertGetId([
                        'work_order_id' => $workOrder->id,
                        'approver_id' => $approverId,
                        'requested_by' => $request->user()->id,
                        'status' => 'pending',
                        'notes' => $validated['notes'] ?? null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $workOrder->update(['status' => WorkOrder::STATUS_WAITING_APPROVAL]);

                $workOrder->statusHistory()->create([
                    'tenant_id' => $workOrder->tenant_id,
                    'user_id' => $request->user()->id,
                    'from_status' => $fromStatus,
                    'to_status' => WorkOrder::STATUS_WAITING_APPROVAL,
                    'notes' => $validated['notes'] ?? 'Aprovação solicitada',
                ]);

                $workOrder->chats()->create([
                    'tenant_id' => $workOrder->tenant_id,
                    'user_id' => $request->user()->id,
                    'type' => 'system',
                    'message' => 'OS enviada para **Aprovação**.',
                ]);

                return $created;
            });

            return ApiResponse::data([
                'approval_ids' => $approvals,
            ], 201, ['message' => 'Aprovação solicitada']);
        } catch (\Throwable $e) {
            Log::error('WO approval request failed', ['wo_id' => $workOrder->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao solicitar aprovação', 500);
        }
    }

    public function respond(RespondWorkOrderApprovalRequest $request, WorkOrder $workOrder, int $approverId, string $action): JsonResponse
    {
        $this->authorize('update', $workOrder);

        if ($response = $this->ensureTenantScope($request, $workOrder)) {
            return $response;
        }

        if ((int) $request->user()->id !== $approverId) {
            return ApiResponse::message('Voce so pode responder a sua propria aprovacao.', 403);
        }

        if (! in_array($action, ['approve', 'reject'], true)) {
            return ApiResponse::message('Ação inválida', 422);
        }

        $validated = $request->validated();

        if (! $this->userBelongsToTenant($approverId, $this->tenantId())) {
            return ApiResponse::message('Aprovador não pertence ao tenant atual.', 403);
        }

        try {
            DB::transaction(function () use ($workOrder, $approverId, $action, $validated, $request) {
                $pendingApproval = DB::table('work_order_approvals')
                    ->where('work_order_id', $workOrder->id)
                    ->where('approver_id', $approverId)
                    ->where('status', 'pending')
                    ->first();

                if (! $pendingApproval) {
                    throw new \DomainException('PENDING_APPROVAL_NOT_FOUND');
                }

                DB::table('work_order_approvals')
                    ->where('id', $pendingApproval->id)
                    ->update([
                        'status' => $action === 'approve' ? 'approved' : 'rejected',
                        'responded_at' => now(),
                        'response_notes' => $validated['notes'] ?? null,
                        'updated_at' => now(),
                    ]);

                if ($action === 'reject') {
                    DB::table('work_order_approvals')
                        ->where('work_order_id', $workOrder->id)
                        ->where('status', 'pending')
                        ->update(['status' => 'cancelled', 'updated_at' => now()]);

                    $previousStatus = $this->resolveStatusBeforeApproval($workOrder);
                    $workOrder->update(['status' => $previousStatus]);

                    $workOrder->statusHistory()->create([
                        'tenant_id' => $workOrder->tenant_id,
                        'user_id' => $request->user()->id,
                        'from_status' => WorkOrder::STATUS_WAITING_APPROVAL,
                        'to_status' => $previousStatus,
                        'notes' => $validated['notes'] ?? 'Aprovação rejeitada',
                    ]);

                    $workOrder->chats()->create([
                        'tenant_id' => $workOrder->tenant_id,
                        'user_id' => $request->user()->id,
                        'type' => 'system',
                        'message' => 'Aprovação rejeitada. OS retornou ao status anterior.',
                    ]);

                    return;
                }

                $allApproved = DB::table('work_order_approvals')
                    ->where('work_order_id', $workOrder->id)
                    ->where('status', 'pending')
                    ->doesntExist();

                if (! $allApproved) {
                    return;
                }

                $this->ensureChecklistCompleted($workOrder);

                $workOrder->update(['status' => WorkOrder::STATUS_COMPLETED]);

                $workOrder->statusHistory()->create([
                    'tenant_id' => $workOrder->tenant_id,
                    'user_id' => $request->user()->id,
                    'from_status' => WorkOrder::STATUS_WAITING_APPROVAL,
                    'to_status' => WorkOrder::STATUS_COMPLETED,
                    'notes' => $validated['notes'] ?? 'Aprovação concluída',
                ]);

                $workOrder->chats()->create([
                    'tenant_id' => $workOrder->tenant_id,
                    'user_id' => $request->user()->id,
                    'type' => 'system',
                    'message' => 'Aprovação concluída. OS retornou para **Concluída**.',
                ]);
            });

            return ApiResponse::message($action === 'approve' ? 'Aprovado' : 'Rejeitado');
        } catch (\DomainException $e) {
            if ($e->getMessage() === 'PENDING_APPROVAL_NOT_FOUND') {
                return ApiResponse::message('Aprovação pendente não encontrada.', 404);
            }

            if ($e->getMessage() === 'CHECKLIST_INCOMPLETE') {
                return ApiResponse::message('O checklist da OS está incompleto. Todos os itens obrigatórios devem ser respondidos antes da aprovação final.', 422);
            }

            Log::warning('WO approval respond domain error', ['wo_id' => $workOrder->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao responder aprovação', 500);
        } catch (\Throwable $e) {
            Log::error('WO approval respond failed', ['wo_id' => $workOrder->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao responder aprovação', 500);
        }
    }

    private function resolveStatusBeforeApproval(WorkOrder $workOrder): string
    {
        $lastApprovalHistory = $workOrder->statusHistory()
            ->where('to_status', WorkOrder::STATUS_WAITING_APPROVAL)
            ->latest('id')
            ->first();

        $fromStatus = $lastApprovalHistory?->from_status?->value;

        return in_array($fromStatus, self::APPROVAL_ELIGIBLE_STATUSES, true)
            ? $fromStatus
            : WorkOrder::STATUS_OPEN;
    }

    private function ensureChecklistCompleted(WorkOrder $workOrder): void
    {
        if (! $workOrder->checklist_id) {
            return;
        }

        $requiredItemsCount = $workOrder->checklist?->items()->count() ?? 0;
        $providedResponsesCount = $workOrder->checklistResponses()->count();

        if ($requiredItemsCount > 0 && $providedResponsesCount < $requiredItemsCount) {
            throw new \DomainException('CHECKLIST_INCOMPLETE');
        }
    }
}
