<?php

namespace App\Http\Controllers\Api\V1\Os;

use App\Enums\AuditAction;
use App\Enums\CommissionEventStatus;
use App\Enums\FinancialStatus;
use App\Enums\FiscalNoteStatus;
use App\Events\WorkOrderCancelled;
use App\Events\WorkOrderCompleted;
use App\Events\WorkOrderInvoiced;
use App\Events\WorkOrderStarted;
use App\Http\Controllers\Controller;
use App\Http\Requests\Os\AuthorizeDispatchRequest;
use App\Http\Requests\Os\DuplicateWorkOrderRequest;
use App\Http\Requests\Os\ReopenWorkOrderRequest;
use App\Http\Requests\Os\StoreWorkOrderSignatureRequest;
use App\Http\Requests\Os\UpdateWorkOrderStatusRequest;
use App\Http\Resources\WorkOrderResource;
use App\Models\AccountReceivable;
use App\Models\AuditLog;
use App\Models\CommissionEvent;
use App\Models\Equipment;
use App\Models\FiscalNote;
use App\Models\InmetroSeal;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Role;
use App\Models\ServiceChecklist;
use App\Models\SystemSetting;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderEvent;
use App\Models\WorkOrderItem;
use App\Models\WorkOrderSignature;
use App\Models\WorkOrderStatusHistory;
use App\Modules\OrdemServico\DTO\OrdemServicoFinalizadaPayload;
use App\Modules\OrdemServico\Events\OrdemServicoFinalizadaEvent;
use App\Notifications\WorkOrderStatusChanged;
use App\Services\StockService;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class WorkOrderActionController extends Controller
{
    use ResolvesCurrentTenant;

    public function duplicate(DuplicateWorkOrderRequest $request, WorkOrder $workOrder): JsonResponse
    {
        $this->authorize('create', WorkOrder::class);
        if ($error = $this->ensureTenantOwnership($workOrder, 'OS')) {
            return $error;
        }
        $tenantId = $this->tenantId();

        try {
            // Eager load relations needed for cloning to avoid N+1
            $workOrder->load(['items', 'equipmentsList', 'technicians']);

            DB::beginTransaction();

            $newOrder = $workOrder->replicate([
                'number', 'os_number', 'status',
                'started_at', 'completed_at', 'delivered_at', 'cancelled_at', 'cancellation_reason',
                'signature_path', 'signature_signer', 'signature_at', 'signature_ip',
                'sla_responded_at', 'dispatch_authorized_by', 'dispatch_authorized_at',
                'displacement_started_at', 'displacement_arrived_at', 'displacement_duration_minutes',
                'received_at', 'agreed_payment_method', 'agreed_payment_notes',
                'service_started_at', 'wait_time_minutes', 'service_duration_minutes', 'total_duration_minutes',
                'arrival_latitude', 'arrival_longitude',
                'checkin_at', 'checkin_lat', 'checkin_lng',
                'checkout_at', 'checkout_lat', 'checkout_lng',
                'auto_km_calculated',
                'return_started_at', 'return_arrived_at', 'return_duration_minutes', 'return_destination',
            ]);
            $newOrder->number = WorkOrder::nextNumber($tenantId);
            $newOrder->status = WorkOrder::STATUS_OPEN;
            $newOrder->created_by = $request->user()->id;
            $newOrder->total = '0.00';
            $newOrder->save();

            // Clone items
            foreach ($workOrder->items as $item) {
                $newItem = $item->replicate(['work_order_id']);
                $newItem->work_order_id = $newOrder->id;
                $newItem->save();
            }

            // Clone equipment links (using already eager-loaded relation)
            $equipIds = $workOrder->equipmentsList->pluck('id')->toArray();
            if (! empty($equipIds)) {
                $newOrder->equipmentsList()->attach(
                    array_fill_keys($equipIds, ['tenant_id' => $newOrder->tenant_id])
                );
            }

            // Clone technicians with pivot role (using already eager-loaded relation)
            $syncData = [];
            foreach ($workOrder->technicians as $tech) {
                $syncData[$tech->id] = [
                    'role' => $tech->pivot->role ?? 'tecnico',
                    'tenant_id' => $newOrder->tenant_id,
                ];
            }
            if (! empty($syncData)) {
                $newOrder->technicians()->attach($syncData);
            }

            $newOrder->recalculateTotal();

            DB::commit();

            return ApiResponse::data(
                new WorkOrderResource($newOrder->fresh()->load(['customer', 'items', 'equipmentsList'])),
                201,
                ['message' => 'OS duplicada com sucesso']
            );
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('WorkOrder duplicate failed', ['source_id' => $workOrder->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao duplicar OS', 500);
        }
    }

    public function uninvoice(WorkOrder $workOrder): JsonResponse
    {
        $this->authorize('changeStatus', $workOrder);
        if ($error = $this->ensureTenantOwnership($workOrder, 'OS')) {
            return $error;
        }

        if ($workOrder->status !== WorkOrder::STATUS_INVOICED) {
            return ApiResponse::message('Apenas OS com status Faturada pode ser desfaturada', 422);
        }

        // Verificar se há Notas Fiscais autorizadas vinculadas
        $hasAuthorizedFiscalNotes = FiscalNote::where('work_order_id', $workOrder->id)
            ->where('status', FiscalNoteStatus::AUTHORIZED)
            ->exists();

        if ($hasAuthorizedFiscalNotes) {
            return ApiResponse::message(
                'Não é possível desfaturar — existe Nota Fiscal autorizada vinculada a esta OS. Cancele a Nota Fiscal antes de desfaturar.',
                422
            );
        }

        // Verificar se há pagamentos realizados nos ARs vinculados
        $receivables = AccountReceivable::where('work_order_id', $workOrder->id)
            ->where('status', '!=', FinancialStatus::CANCELLED)
            ->get();

        $hasPaidReceivables = $receivables->contains(fn ($ar) => bccomp((string) $ar->amount_paid, '0', 2) > 0);

        if ($hasPaidReceivables) {
            return ApiResponse::message(
                'Não é possível desfaturar — existem pagamentos já realizados nos títulos. Estorne os pagamentos primeiro.',
                422
            );
        }

        $invoicedCommissionEvents = CommissionEvent::query()
            ->where('work_order_id', $workOrder->id)
            ->where('notes', 'like', '%trigger:os_invoiced%')
            ->get();

        $hasPaidInvoicedCommissions = $invoicedCommissionEvents->contains(
            fn ($event) => ($event->status instanceof CommissionEventStatus
                ? $event->status->value
                : (string) $event->status) === CommissionEvent::STATUS_PAID
        );

        if ($hasPaidInvoicedCommissions) {
            return ApiResponse::message(
                'Não é possível desfaturar — existem comissões de faturamento já pagas. Estorne as comissões primeiro.',
                422
            );
        }

        DB::beginTransaction();

        try {
            // 1. Cancelar Invoices vinculadas
            Invoice::where('work_order_id', $workOrder->id)
                ->where('status', '!=', Invoice::STATUS_CANCELLED)
                ->update(['status' => Invoice::STATUS_CANCELLED]);

            // 2. Cancelar AccountReceivables
            foreach ($receivables as $ar) {
                $ar->forceFill(['status' => FinancialStatus::CANCELLED])->saveQuietly();
            }

            // 3. Estornar apenas comissões geradas no faturamento
            $invoicedCommissionEvents
                ->filter(function (CommissionEvent $event): bool {
                    $status = $event->status instanceof CommissionEventStatus
                        ? $event->status->value
                        : (string) $event->status;

                    return in_array($status, [
                        CommissionEvent::STATUS_PENDING,
                        CommissionEvent::STATUS_APPROVED,
                    ], true);
                })
                ->each(function (CommissionEvent $event): void {
                    $event->update([
                        'status' => CommissionEvent::STATUS_REVERSED,
                        'notes' => trim(($event->notes ?? '').' | Estornado: desfaturamento manual em '.now()->format('d/m/Y H:i')),
                    ]);
                });

            // 4. Reverter status da OS para delivered
            $from = $workOrder->status;
            $workOrder->updateQuietly(['status' => WorkOrder::STATUS_DELIVERED]);

            // 5. Registrar no histórico
            $actorId = auth()->id();
            $actorId = is_numeric($actorId) ? (int) $actorId : null;
            $workOrder->statusHistory()->create([
                'tenant_id' => $workOrder->tenant_id,
                'user_id' => $actorId,
                'from_status' => $from,
                'to_status' => WorkOrder::STATUS_DELIVERED,
                'notes' => 'Faturamento cancelado (desfaturamento manual)',
            ]);

            $this->recordStatusTimelineEvent(
                $workOrder,
                $actorId,
                $from,
                WorkOrder::STATUS_DELIVERED,
                'Faturamento cancelado (desfaturamento manual)'
            );

            // 6. Mensagem no chat da OS
            $workOrder->chats()->create([
                'tenant_id' => $workOrder->tenant_id,
                'user_id' => auth()->id(),
                'type' => 'system',
                'message' => 'Faturamento cancelado. OS revertida para **Entregue**.',
            ]);

            DB::commit();

            AuditLog::log('uninvoiced', "OS {$workOrder->business_number} desfaturada", $workOrder, ['status' => WorkOrder::STATUS_INVOICED], ['status' => WorkOrder::STATUS_DELIVERED]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('WorkOrder uninvoice failed', ['id' => $workOrder->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao desfaturar OS', 500);
        }

        return ApiResponse::data(
            new WorkOrderResource($workOrder->fresh()->load(['customer:id,name', 'statusHistory.user:id,name'])),
            200,
            ['message' => 'OS desfaturada com sucesso. Invoice e títulos financeiros cancelados.']
        );
    }

    public function reopen(ReopenWorkOrderRequest $request, WorkOrder $workOrder): JsonResponse
    {
        if ($error = $this->ensureTenantOwnership($workOrder, 'OS')) {
            return $error;
        }

        try {
            DB::beginTransaction();

            $workOrder->update([
                'status' => WorkOrder::STATUS_OPEN,
                'cancelled_at' => null,
                'cancellation_reason' => null,
            ]);

            $stockService = app(StockService::class);
            $workOrder->loadMissing('items');

            foreach ($workOrder->items as $item) {
                if ($item->type !== WorkOrderItem::TYPE_PRODUCT || ! $item->reference_id) {
                    continue;
                }

                $product = Product::query()->find($item->reference_id);
                if (! $product || ! $product->track_stock) {
                    continue;
                }

                $stockService->reserve($product, (float) $item->quantity, $workOrder, $item->warehouse_id);
            }

            WorkOrderStatusHistory::create([
                'tenant_id' => $workOrder->tenant_id,
                'work_order_id' => $workOrder->id,
                'user_id' => $request->user()->id,
                'from_status' => WorkOrder::STATUS_CANCELLED,
                'to_status' => WorkOrder::STATUS_OPEN,
                'notes' => 'OS reaberta',
            ]);

            $this->recordStatusTimelineEvent(
                $workOrder,
                $request->user()->id,
                WorkOrder::STATUS_CANCELLED,
                WorkOrder::STATUS_OPEN,
                'OS reaberta'
            );

            $workOrder->chats()->create([
                'tenant_id' => $workOrder->tenant_id,
                'user_id' => $request->user()->id,
                'type' => 'system',
                'message' => 'OS reaberta e retornou para **Aberta**.',
            ]);

            DB::commit();

            return ApiResponse::data(new WorkOrderResource($workOrder->fresh()->load(['customer:id,name,latitude,longitude', 'statusHistory.user:id,name'])));
        } catch (ValidationException $e) {
            DB::rollBack();

            return ApiResponse::message(
                'Nao foi possivel reabrir a OS porque o estoque reservado nao esta mais disponivel.',
                422,
                ['errors' => $e->errors()]
            );
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('WorkOrder reopen failed', ['id' => $workOrder->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao reabrir OS', 500);
        }
    }

    public function authorizeDispatch(AuthorizeDispatchRequest $request, WorkOrder $workOrder): JsonResponse
    {
        if ($error = $this->ensureTenantOwnership($workOrder, 'OS')) {
            return $error;
        }

        try {
            DB::beginTransaction();

            $workOrder->update([
                'dispatch_authorized_by' => $request->user()->id,
                'dispatch_authorized_at' => now(),
            ]);

            WorkOrderStatusHistory::create([
                'tenant_id' => $workOrder->tenant_id,
                'work_order_id' => $workOrder->id,
                'user_id' => $request->user()->id,
                'from_status' => $workOrder->status,
                'to_status' => $workOrder->status,
                'notes' => 'Deslocamento autorizado',
            ]);

            $this->recordStatusTimelineEvent(
                $workOrder,
                $request->user()->id,
                $workOrder->status,
                $workOrder->status,
                'Deslocamento autorizado'
            );

            DB::commit();

            return ApiResponse::data(new WorkOrderResource($workOrder->fresh()->load(['customer:id,name,latitude,longitude', 'statusHistory.user:id,name', 'driver:id,name'])));
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('WorkOrder authorizeDispatch failed', ['id' => $workOrder->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao autorizar deslocamento', 500);
        }
    }

    public function updateStatus(UpdateWorkOrderStatusRequest $request, WorkOrder $workOrder): JsonResponse
    {
        $this->authorize('changeStatus', $workOrder);
        if ($error = $this->ensureTenantOwnership($workOrder, 'OS')) {
            return $error;
        }

        $validated = $request->validated();
        $to = $validated['status'];
        $from = $workOrder->status;

        DB::beginTransaction();

        try {
            // Lock para evitar race condition em transições concorrentes
            $workOrder = WorkOrder::lockForUpdate()->find($workOrder->id);
            $from = $workOrder->status;

            if (! $workOrder->canTransitionTo($to)) {
                DB::rollBack();
                $fromLabel = WorkOrder::STATUSES[$from]['label'] ?? $from;
                $toLabel = WorkOrder::STATUSES[$to]['label'] ?? $to;

                return ApiResponse::message("Transição inválida: {$fromLabel} → {$toLabel}", 422, ['allowed' => WorkOrder::ALLOWED_TRANSITIONS[$from] ?? []]);
            }

            // Guard: forma de pagamento obrigatória para DELIVERED e INVOICED (verificar antes dos demais guards)
            if (in_array($to, [WorkOrder::STATUS_DELIVERED, WorkOrder::STATUS_INVOICED], true)) {
                $method = trim((string) ($validated['agreed_payment_method'] ?? ''));
                $validMethods = array_keys(WorkOrder::AGREED_PAYMENT_METHODS);
                if ($method === '' || ! in_array($method, $validMethods, true)) {
                    DB::rollBack();

                    return ApiResponse::message('Informe a forma de pagamento acordada com o cliente (ou "A combinar após emissão da nota").', 422, ['errors' => ['agreed_payment_method' => ['Campo obrigatório ao marcar Entregue ou Faturada.']]]);
                }
            }

            // Guard: faturamento requer pelo menos 1 item
            if ($to === WorkOrder::STATUS_INVOICED && $workOrder->items()->count() === 0) {
                DB::rollBack();

                return ApiResponse::message('Não é possível faturar uma OS sem itens. Adicione pelo menos um produto ou serviço.', 422);
            }

            // Guard: conclusão requer técnico atribuído
            if ($to === WorkOrder::STATUS_COMPLETED && ! $workOrder->assigned_to && $workOrder->technicians()->count() === 0) {
                DB::rollBack();

                return ApiResponse::message('Não é possível concluir uma OS sem técnico atribuído.', 422);
            }

            if ($to === WorkOrder::STATUS_COMPLETED && $workOrder->checklist_id) {
                $checklist = $workOrder->checklist;
                $requiredItemsCount = $checklist instanceof ServiceChecklist ? $checklist->items()->count() : 0;
                $providedResponsesCount = $workOrder->checklistResponses()->count();

                if ($requiredItemsCount > 0 && $providedResponsesCount < $requiredItemsCount) {
                    DB::rollBack();

                    return ApiResponse::message('O checklist desta OS está incompleto. Todos os itens devem ser respondidos antes de concluir.', 422, ['required_items' => $requiredItemsCount, 'provided_responses' => $providedResponsesCount]);
                }
            }

            // Guard: assinatura obrigatória se configurado no tenant
            if ($to === WorkOrder::STATUS_COMPLETED) {
                $requireSignature = SystemSetting::where('tenant_id', $workOrder->tenant_id)
                    ->where('key', 'require_signature_on_completion')
                    ->value('value');

                if ($requireSignature && ! $workOrder->signatures()->exists()) {
                    DB::rollBack();

                    return ApiResponse::message('Assinatura do cliente é obrigatória para concluir esta OS. Solicite a assinatura antes de concluir.', 422);
                }
            }

            // Guard: selo de reparo + lacre obrigatórios para OS de calibração/reparo (configurável por tenant)
            if ($to === WorkOrder::STATUS_COMPLETED) {
                $requireSeal = SystemSetting::where('tenant_id', $workOrder->tenant_id)
                    ->where('key', 'require_seal_on_completion')
                    ->value('value');

                if ($requireSeal) {
                    $sealStatuses = [
                        InmetroSeal::STATUS_USED,
                        InmetroSeal::STATUS_PENDING_PSEI,
                        InmetroSeal::STATUS_REGISTERED,
                    ];

                    $hasSelo = InmetroSeal::where('work_order_id', $workOrder->id)
                        ->where('type', InmetroSeal::TYPE_SELO_REPARO)
                        ->whereIn('status', $sealStatuses)
                        ->exists();

                    $hasLacre = InmetroSeal::where('work_order_id', $workOrder->id)
                        ->where('type', InmetroSeal::TYPE_LACRE)
                        ->whereIn('status', $sealStatuses)
                        ->exists();

                    if (! $hasSelo || ! $hasLacre) {
                        $missing = [];
                        if (! $hasSelo) {
                            $missing[] = 'selo de reparo';
                        }
                        if (! $hasLacre) {
                            $missing[] = 'lacre';
                        }
                        DB::rollBack();

                        return ApiResponse::message('Vincule '.implode(' e ', $missing).' antes de concluir a OS. Use o módulo Selos de Reparo para registrar o uso.', 422);
                    }
                }
            }

            $updateData = [
                'status' => $to,
                'started_at' => in_array($to, [WorkOrder::STATUS_IN_PROGRESS, WorkOrder::STATUS_IN_DISPLACEMENT, WorkOrder::STATUS_IN_SERVICE]) && ! $workOrder->started_at ? now() : $workOrder->started_at,
                'completed_at' => $to === WorkOrder::STATUS_COMPLETED ? now() : $workOrder->completed_at,
                'delivered_at' => $to === WorkOrder::STATUS_DELIVERED ? now() : $workOrder->delivered_at,
            ];

            // SLA: marcar sla_responded_at na primeira ação (saindo de OPEN)
            if ($from === WorkOrder::STATUS_OPEN && $to !== WorkOrder::STATUS_CANCELLED && ! $workOrder->sla_responded_at) {
                $updateData['sla_responded_at'] = now();
            }

            // Registrar dados de cancelamento
            if ($to === WorkOrder::STATUS_CANCELLED) {
                $updateData['cancelled_at'] = now();
                $updateData['cancellation_reason'] = $validated['notes'] ?? null;
            }

            // NOTE: Stock reversal on cancel is handled by HandleWorkOrderCancellation listener
            // via WorkOrderCancelled event (dispatched after commit). Do NOT duplicate here.

            if (in_array($to, [WorkOrder::STATUS_DELIVERED, WorkOrder::STATUS_INVOICED])) {
                $updateData['agreed_payment_method'] = $validated['agreed_payment_method'] ?? null;
                $updateData['agreed_payment_notes'] = $validated['agreed_payment_notes'] ?? null;
            }

            $workOrder->update($updateData);

            // statusHistory criado apenas para transições sem Listener dedicado
            // Os Listeners WorkOrderStarted, Completed, Cancelled, Invoiced já criam seus próprios registros
            if (! in_array($to, [WorkOrder::STATUS_IN_PROGRESS, WorkOrder::STATUS_COMPLETED, WorkOrder::STATUS_CANCELLED, WorkOrder::STATUS_INVOICED])) {
                $workOrder->statusHistory()->create([
                    'tenant_id' => $workOrder->tenant_id,
                    'user_id' => $request->user()->id,
                    'from_status' => $from,
                    'to_status' => $to,
                    'notes' => $validated['notes'] ?? null,
                ]);
            }

            $this->recordStatusTimelineEvent(
                $workOrder,
                $request->user()->id,
                $from,
                $to,
                $validated['notes'] ?? null
            );

            // Automate System Chat Message for Status Change (Brainstorm #13 / Wave 1)
            $workOrder->chats()->create([
                'tenant_id' => $workOrder->tenant_id,
                'user_id' => $request->user()->id,
                'type' => 'system',
                'message' => 'OS alterada de **'.(WorkOrder::STATUSES[$from]['label'] ?? $from).'** para **'.(WorkOrder::STATUSES[$to]['label'] ?? $to).'**'.(isset($validated['notes']) && $validated['notes'] ? ": {$validated['notes']}" : ''),
            ]);

            // #26 — Notificar técnico responsável e criador
            if (in_array($to, [WorkOrder::STATUS_COMPLETED, WorkOrder::STATUS_DELIVERED, WorkOrder::STATUS_CANCELLED, WorkOrder::STATUS_WAITING_APPROVAL])) {
                $notification = new WorkOrderStatusChanged($workOrder, $from, $to);
                $notifyIds = array_filter(array_unique([
                    $workOrder->assigned_to,
                    $workOrder->created_by,
                ]));
                $usersToNotify = User::whereIn('id', $notifyIds)
                    ->where('id', '!=', $request->user()->id)
                    ->get();
                foreach ($usersToNotify as $u) {
                    // Persist in KALIBRIUM's custom notifications table
                    $notification->persistToDatabase($workOrder->tenant_id, $u->id);

                    // Send email (mail channel only, no database channel)
                    try {
                        $u->notify($notification);
                    } catch (\Throwable $mailEx) {
                        Log::warning('WO status email notification failed', [
                            'wo_id' => $workOrder->id, 'user_id' => $u->id,
                            'error' => $mailEx->getMessage(),
                        ]);
                    }
                }
            }

            if (in_array($to, [WorkOrder::STATUS_COMPLETED, WorkOrder::STATUS_DELIVERED])) {
                $workOrder->loadMissing('customer');
                if ($workOrder->customer) {
                    try {
                        $workOrder->customer->notify(new WorkOrderStatusChanged($workOrder, $from, $to));
                    } catch (\Throwable $e) {
                        Log::warning('Customer notification failed', ['wo_id' => $workOrder->id, 'error' => $e->getMessage()]);
                    }
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('WorkOrder status update failed', [
                'work_order_id' => $workOrder->id,
                'from' => $from,
                'to' => $to,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::message('Erro ao alterar status da OS', 500);
        }

        // Dispatch domain events AFTER commit in separate try-catch
        // Falha em event/listener não deve retornar 500 ao usuário (dados já salvos)
        try {
            if ($to === WorkOrder::STATUS_INVOICED) {
                // NOTE: Invoice + AR generation is handled by HandleWorkOrderInvoicing listener
                // via InvoicingService::generateFromWorkOrder() — which supports installments.
                // Do NOT call WorkOrderInvoicingService here to avoid duplicate AR creation.
            }

            $user = $request->user();
            match ($to) {
                WorkOrder::STATUS_IN_PROGRESS, WorkOrder::STATUS_IN_DISPLACEMENT => WorkOrderStarted::dispatch($workOrder, $user, $from),
                WorkOrder::STATUS_COMPLETED => [
                    WorkOrderCompleted::dispatch($workOrder, $user, $from),
                    OrdemServicoFinalizadaEvent::dispatch(
                        OrdemServicoFinalizadaPayload::fromWorkOrder($workOrder, $user)
                    ),
                ],
                WorkOrder::STATUS_INVOICED => WorkOrderInvoiced::dispatch($workOrder, $user, $from),
                WorkOrder::STATUS_CANCELLED => WorkOrderCancelled::dispatch($workOrder, $user, $validated['notes'] ?? '', $from),
                default => null,
            };

            event(new \App\Events\WorkOrderStatusChanged($workOrder));
        } catch (\Exception $eventEx) {
            Log::warning('WorkOrder event dispatch failed (data already committed)', [
                'work_order_id' => $workOrder->id,
                'to_status' => $to,
                'error' => $eventEx->getMessage(),
            ]);
        }

        return ApiResponse::data(new WorkOrderResource($workOrder->fresh()->load(['customer:id,name,latitude,longitude', 'statusHistory.user:id,name'])));
    }

    public function restore(int $id): JsonResponse
    {
        $tenantId = $this->tenantId();
        $workOrder = WorkOrder::onlyTrashed()
            ->where('tenant_id', $tenantId)
            ->findOrFail($id);

        $this->authorize('delete', $workOrder);

        try {
            DB::beginTransaction();

            $workOrder->restore();

            // Re-reservar estoque dos itens de produto
            $stockService = app(StockService::class);
            foreach ($workOrder->items as $item) {
                if ($item->type !== WorkOrderItem::TYPE_PRODUCT || ! $item->reference_id) {
                    continue;
                }
                $product = Product::find($item->reference_id);
                if ($product && $product->track_stock) {
                    $stockService->reserve($product, (float) $item->quantity, $workOrder, $item->warehouse_id);
                }
            }

            AuditLog::log('restored', "OS {$workOrder->business_number} restaurada", $workOrder, [], ['status' => $workOrder->status]);

            DB::commit();

            return ApiResponse::data(
                new WorkOrderResource($workOrder->fresh()->load(['customer:id,name', 'statusHistory.user:id,name'])),
                200,
                ['message' => 'OS restaurada com sucesso.']
            );
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('WorkOrder restore failed', ['id' => $id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao restaurar OS', 500);
        }
    }

    public function storeSignature(WorkOrder $workOrder, StoreWorkOrderSignatureRequest $request): JsonResponse
    {
        $this->authorize('update', $workOrder);
        if ($error = $this->ensureTenantOwnership($workOrder, 'OS')) {
            return $error;
        }

        $validated = $request->validated();

        if (! in_array($workOrder->status, [WorkOrder::STATUS_AWAITING_RETURN, WorkOrder::STATUS_IN_RETURN, WorkOrder::STATUS_RETURN_PAUSED, WorkOrder::STATUS_COMPLETED, WorkOrder::STATUS_DELIVERED])) {
            return ApiResponse::message('Assinatura só pode ser registrada após a conclusão do serviço', 422);
        }

        $previousSignaturePath = $workOrder->signature_path;
        $transactionStarted = false;
        $path = null;

        try {
            $signatureData = preg_replace('#^data:image/\w+;base64,#i', '', (string) $validated['signature_data']);
            $imageData = $signatureData === null ? false : base64_decode($signatureData, true);

            if ($imageData === false) {
                return ApiResponse::message('Assinatura invalida.', 422);
            }

            $path = "signatures/wo_{$workOrder->id}_".time().'.png';
            Storage::disk('public')->put($path, $imageData);

            DB::beginTransaction();
            $transactionStarted = true;

            $signedAt = now();

            $workOrder->update([
                'signature_path' => $path,
                'signature_signer' => $validated['signer_name'],
                'signature_at' => $signedAt,
                'signature_ip' => $request->ip(),
            ]);

            $signature = WorkOrderSignature::create([
                'tenant_id' => $this->tenantId(),
                'work_order_id' => $workOrder->id,
                'signer_name' => $validated['signer_name'],
                'signer_document' => $validated['signer_document'] ?? null,
                'signer_type' => $validated['signer_type'],
                'signature_data' => $validated['signature_data'],
                'signed_at' => $signedAt,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            AuditLog::log(
                AuditAction::UPDATED,
                "OS {$workOrder->business_number}: assinatura registrada",
                $workOrder,
                ['signature_path' => $previousSignaturePath],
                ['signature_path' => $path, 'signature_signer' => $validated['signer_name']]
            );

            DB::commit();

            if ($previousSignaturePath && $previousSignaturePath !== $path) {
                Storage::disk('public')->delete($previousSignaturePath);
            }

            return ApiResponse::data([
                'signature_id' => $signature->id,
                'signature_url' => asset("storage/{$path}"),
            ], 200, [
                'message' => 'Assinatura registrada com sucesso',
            ]);
        } catch (\Exception $e) {
            if ($transactionStarted) {
                DB::rollBack();
            }
            Storage::disk('public')->delete($path);
            Log::error('WorkOrder storeSignature failed', ['wo_id' => $workOrder->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao salvar assinatura', 500);
        }
    }

    /**
     * @return mixed
     */
    public function downloadPdf(WorkOrder $workOrder)
    {
        $this->authorize('view', $workOrder);
        if ($error = $this->ensureTenantOwnership($workOrder, 'OS')) {
            return $error;
        }

        $workOrder->load([
            'customer',
            'items',
            'equipment',
            'equipmentsList',
            'assignee',
            'seller',
            'creator',
            'driver',
            'statusHistory',
            'attachments',
        ]);

        try {
            $tenant = Tenant::find($workOrder->tenant_id);

            $data = [
                'workOrder' => $workOrder,
                'order' => $workOrder,
                'tenant' => $tenant,
                'items' => $workOrder->items,
                'customer' => $workOrder->customer,
                'equipments' => $workOrder->equipmentsList ?? collect(),
                'technician' => $workOrder->assignee,
                'driver' => $workOrder->driver,
            ];

            $totals = $workOrder->calculateFinancialTotals();

            $data['subtotal'] = number_format((float) $totals['items_subtotal'], 2, ',', '.');
            $data['items_discount'] = number_format((float) $totals['items_discount'], 2, ',', '.');
            $data['items_net_subtotal'] = number_format((float) $totals['items_net_subtotal'], 2, ',', '.');
            $data['discount'] = number_format((float) $totals['global_discount'], 2, ',', '.');
            $data['displacement'] = number_format((float) $totals['displacement_value'], 2, ',', '.');
            $data['total'] = number_format((float) $totals['grand_total'], 2, ',', '.');

            // Use Blade template if available, otherwise generate simple HTML
            $viewName = (string) config('pdf.work_order_view', 'pdf.work-order');
            if (view()->exists($viewName)) {
                $html = view($viewName, $data)->render();
            } else {
                $html = $this->generatePdfHtml($data);
            }

            $pdf = Pdf::loadHTML($html)
                ->setPaper('a4', 'portrait');

            $filename = "os-{$workOrder->id}.pdf";

            return $pdf->download($filename);
        } catch (\Exception $e) {
            Log::error('WorkOrder PDF generation failed', [
                'work_order_id' => $workOrder->id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::message('Erro ao gerar PDF da OS', 500);
        }
    }

    private function recordStatusTimelineEvent(
        WorkOrder $workOrder,
        ?int $userId,
        ?string $fromStatus,
        string $toStatus,
        ?string $notes = null
    ): void {
        WorkOrderEvent::create([
            'tenant_id' => $workOrder->tenant_id,
            'work_order_id' => $workOrder->id,
            'event_type' => WorkOrderEvent::TYPE_STATUS_CHANGED,
            'user_id' => $userId,
            'metadata' => [
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'notes' => $notes,
                'from_label' => $fromStatus ? (WorkOrder::STATUSES[$fromStatus]['label'] ?? $fromStatus) : null,
                'to_label' => WorkOrder::STATUSES[$toStatus]['label'] ?? $toStatus,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function generatePdfHtml(array $data): string
    {
        $order = $data['order'];
        $customer = $data['customer'];
        $tenant = $data['tenant'];
        $items = $data['items'];

        $statusLabel = WorkOrder::STATUSES[$order->status]['label'] ?? $order->status;
        $priorityLabel = WorkOrder::PRIORITIES[$order->priority]['label'] ?? $order->priority;
        $createdAt = $order->created_at ? $order->created_at->format('d/m/Y H:i') : '—';
        $technician = $data['technician']->name ?? '—';

        $itemsHtml = '';
        foreach ($items as $item) {
            $lineTotal = number_format($item->quantity * $item->unit_price, 2, ',', '.');
            $unitPrice = number_format($item->unit_price, 2, ',', '.');
            $typeLabel = $item->type === 'product' ? 'Produto' : 'Serviço';
            $itemsHtml .= "<tr>
                <td>{$typeLabel}</td>
                <td>{$item->description}</td>
                <td style='text-align:center'>{$item->quantity}</td>
                <td style='text-align:right'>R$ {$unitPrice}</td>
                <td style='text-align:right'>R$ {$lineTotal}</td>
            </tr>";
        }

        if (empty($itemsHtml)) {
            $itemsHtml = '<tr><td colspan="5" style="text-align:center;color:#999">Nenhum item</td></tr>';
        }

        $equipmentsHtml = '';
        foreach ($data['equipments'] as $eq) {
            $equipmentsHtml .= "<li>{$eq->name} — {$eq->brand} {$eq->model} (S/N: {$eq->serial_number})</li>";
        }
        if (empty($equipmentsHtml)) {
            $equipmentsHtml = '<li style="color:#999">Nenhum equipamento vinculado</li>';
        }

        return "<!DOCTYPE html>
<html lang='pt-BR'>
<head><meta charset='UTF-8'>
<style>
  body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 11px; color: #333; margin: 30px; }
  h1 { font-size: 18px; margin-bottom: 4px; }
  h2 { font-size: 13px; border-bottom: 1px solid #ccc; padding-bottom: 4px; margin-top: 20px; }
  table { width: 100%; border-collapse: collapse; margin-top: 8px; }
  th, td { border: 1px solid #ddd; padding: 5px 8px; font-size: 10px; }
  th { background: #f5f5f5; text-align: left; }
  .info-grid { display: table; width: 100%; }
  .info-row { display: table-row; }
  .info-label { display: table-cell; width: 130px; font-weight: bold; padding: 3px 0; }
  .info-value { display: table-cell; padding: 3px 0; }
  .totals { margin-top: 12px; text-align: right; }
  .totals p { margin: 2px 0; }
  .total-final { font-size: 14px; font-weight: bold; }
</style></head>
<body>
  <h1>".($tenant->name ?? 'Empresa')."</h1>
  <p style='color:#666; margin-top:0'>Ordem de Serviço Nº {$order->business_number}</p>

  <h2>Informações Gerais</h2>
  <div class='info-grid'>
    <div class='info-row'><span class='info-label'>Status:</span><span class='info-value'>{$statusLabel}</span></div>
    <div class='info-row'><span class='info-label'>Prioridade:</span><span class='info-value'>{$priorityLabel}</span></div>
    <div class='info-row'><span class='info-label'>Data Criação:</span><span class='info-value'>{$createdAt}</span></div>
    <div class='info-row'><span class='info-label'>Técnico:</span><span class='info-value'>{$technician}</span></div>
  </div>

  <h2>Cliente</h2>
  <div class='info-grid'>
    <div class='info-row'><span class='info-label'>Nome:</span><span class='info-value'>".($customer->name ?? '—')."</span></div>
    <div class='info-row'><span class='info-label'>CNPJ/CPF:</span><span class='info-value'>".($customer->document ?? '—')."</span></div>
    <div class='info-row'><span class='info-label'>Telefone:</span><span class='info-value'>".($customer->phone ?? '—')."</span></div>
    <div class='info-row'><span class='info-label'>Endereço:</span><span class='info-value'>".($customer->address ?? '—')."</span></div>
  </div>

  <h2>Equipamentos</h2>
  <ul>{$equipmentsHtml}</ul>

  <h2>Descrição</h2>
  <p>".($order->description ?? 'Sem descrição')."</p>

  <h2>Itens</h2>
  <table>
    <thead><tr><th>Tipo</th><th>Descrição</th><th style='text-align:center'>Qtd</th><th style='text-align:right'>Preço Unit.</th><th style='text-align:right'>Total</th></tr></thead>
    <tbody>{$itemsHtml}</tbody>
  </table>

  <div class='totals'>
    <p>Subtotal: R$ {$data['subtotal']}</p>
    <p>Deslocamento: R$ {$data['displacement']}</p>
    <p>Desconto: R$ {$data['discount']}</p>
    <p class='total-final'>Total: R$ {$data['total']}</p>
  </div>

  ".($order->technical_report ? "<h2>Laudo Técnico</h2><p>{$order->technical_report}</p>" : '').'
</body></html>';
    }
}
