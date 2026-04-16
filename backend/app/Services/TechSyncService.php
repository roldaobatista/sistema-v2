<?php

namespace App\Services;

use App\Enums\ExpenseStatus;
use App\Enums\ServiceCallStatus;
use App\Models\AuditLog;
use App\Models\Equipment;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\ExpenseStatusHistory;
use App\Models\MaterialRequest;
use App\Models\NpsSurvey;
use App\Models\SealApplication;
use App\Models\ServiceCall;
use App\Models\ServiceChecklist;
use App\Models\StandardWeight;
use App\Models\SyncConflictLog;
use App\Models\TechnicianFeedback;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderAttachment;
use App\Models\WorkOrderChecklistResponse;
use App\Models\WorkOrderDisplacementLocation;
use App\Models\WorkOrderDisplacementStop;
use App\Models\WorkOrderSignature;
use App\Models\WorkOrderStatusHistory;
use App\Support\ApiResponse;
use App\Support\FilenameSanitizer;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * @phpstan-type SyncPayload array<string, mixed>
 * @phpstan-type SyncConflict array<string, mixed>
 */
class TechSyncService
{
    private ?int $tenantId = null;

    private ?User $user = null;

    public function setContext(int $tenantId, User $user): self
    {
        $this->tenantId = $tenantId;
        $this->user = $user;

        return $this;
    }

    /**
     * @param  SyncPayload  $data
     * @param  array<int, SyncConflict>  $conflicts
     */
    private function processChecklistResponse(array $data, array &$conflicts): bool
    {
        $workOrderId = (int) ($data['work_order_id'] ?? 0);
        $workOrder = $this->findAuthorizedWorkOrderOrFail(
            $workOrderId,
            'update',
            'Nao autorizado a sincronizar checklist desta OS.'
        );

        if (isset($data['client_work_order_updated_at'])) {
            $clientUpdated = Carbon::parse($data['client_work_order_updated_at']);
            if ($workOrder->updated_at->gt($clientUpdated)) {
                $conflicts[] = [
                    'type' => 'checklist_response',
                    'id' => $data['id'] ?? 'unknown',
                    'server_updated_at' => $workOrder->updated_at->toISOString(),
                ];

                return false;
            }
        }

        if (! $workOrder->checklist_id) {
            throw new \InvalidArgumentException('OS sem checklist vinculado.');
        }

        $responseItems = $data['responses'] ?? [];
        if (! is_array($responseItems)) {
            $responseItems = [$responseItems];
        }

        /** @var array<array-key, mixed> $responseItems */
        $responses = collect($responseItems);

        if ($responses->isEmpty()) {
            return false;
        }

        $normalizedResponses = $responses
            ->map(function ($value, $key) {
                if (is_array($value) && array_key_exists('checklist_item_id', $value)) {
                    return [
                        'checklist_item_id' => (int) $value['checklist_item_id'],
                        'value' => $this->normalizeChecklistResponseValue($value['value'] ?? null),
                        'notes' => $value['notes'] ?? null,
                    ];
                }

                return [
                    'checklist_item_id' => (int) $key,
                    'value' => $this->normalizeChecklistResponseValue($value),
                    'notes' => null,
                ];
            })
            ->filter(fn (array $response) => $response['checklist_item_id'] > 0)
            ->values();

        if ($normalizedResponses->isEmpty()) {
            return false;
        }

        $validItemIds = DB::table('service_checklist_items')
            ->where('checklist_id', $workOrder->checklist_id)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $validMap = array_flip($validItemIds);

        foreach ($normalizedResponses as $response) {
            if (! isset($validMap[$response['checklist_item_id']])) {
                throw new \InvalidArgumentException('Item de checklist inválido para esta OS.');
            }

            WorkOrderChecklistResponse::updateOrCreate(
                [
                    'work_order_id' => $workOrderId,
                    'checklist_item_id' => $response['checklist_item_id'],
                ],
                [
                    'tenant_id' => $this->tenantId,
                    'value' => $response['value'],
                    'notes' => $response['notes'],
                ]
            );
        }

        AuditLog::log(
            'updated',
            'Respostas do checklist sincronizadas (Origem: PWA Sync)',
            $workOrder
        );

        return true;
    }

    private function processStatusChange(array $data, array &$conflicts): bool
    {
        $workOrder = $this->findAuthorizedWorkOrderOrFail(
            (int) $data['work_order_id'],
            'changeStatus',
            'Nao autorizado a alterar o status desta OS.'
        );
        $targetStatus = $data['to_status'] ?? $data['status'] ?? null;

        if (! is_string($targetStatus) || $targetStatus === '') {
            throw new \InvalidArgumentException('Status alvo não informado.');
        }

        // Conflict check: if server version is newer, report conflict
        if (isset($data['updated_at'])) {
            $clientUpdated = Carbon::parse($data['updated_at']);
            if ($workOrder->updated_at->gt($clientUpdated)) {
                $conflicts[] = [
                    'type' => 'status_change',
                    'id' => (string) $workOrder->id,
                    'server_updated_at' => $workOrder->updated_at->toISOString(),
                ];

                return false;
            }
        }

        $fromStatus = $workOrder->status;
        if ($fromStatus !== $targetStatus && ! $workOrder->canTransitionTo($targetStatus)) {
            throw new \InvalidArgumentException('Transicao de status invalida para esta OS.');
        }
        $workOrder->update(['status' => $targetStatus]);

        if ($fromStatus !== $targetStatus) {
            WorkOrderStatusHistory::create([
                'tenant_id' => $workOrder->tenant_id,
                'work_order_id' => $workOrder->id,
                'user_id' => $this->user->id,
                'from_status' => $fromStatus,
                'to_status' => $targetStatus,
                'notes' => 'Status sincronizado do modo offline',
            ]);

            AuditLog::log(
                'status_changed',
                "OS {$workOrder->business_number}: status sincronizado de {$fromStatus} para {$targetStatus} (Origem: PWA Sync)",
                $workOrder,
                ['status' => $fromStatus],
                ['status' => $targetStatus]
            );
        }

        return true;
    }

    private function processDisplacementStop(array $data, array &$conflicts): bool
    {
        $workOrder = $this->findAuthorizedWorkOrderOrFail((int) $data['work_order_id'], 'changeStatus');
        $type = $data['type'] ?? 'other';
        if (! in_array($type, ['lunch', 'hotel', 'br_stop', 'other'], true)) {
            $type = 'other';
        }
        if (isset($data['ended_at']) && (isset($data['stop_id']) || ! empty($data['end_latest']))) {
            $stop = isset($data['stop_id'])
                ? WorkOrderDisplacementStop::where('work_order_id', $workOrder->id)->where('id', $data['stop_id'])->first()
                : WorkOrderDisplacementStop::where('work_order_id', $workOrder->id)->whereNull('ended_at')->orderByDesc('started_at')->first();
            if ($stop && ! $stop->ended_at) {
                $stop->update(['ended_at' => Carbon::parse($data['ended_at'])]);
                if ($workOrder->displacement_arrived_at) {
                    $this->recalculateDisplacementDuration($workOrder);
                }

                AuditLog::log('updated', 'Pausa de deslocamento encerrada (Origem: PWA Sync)', $workOrder);

                return true;
            }

            return false;
        }
        if (! $workOrder->displacement_started_at || $workOrder->displacement_arrived_at) {
            return false;
        }
        WorkOrderDisplacementStop::create([
            'work_order_id' => $workOrder->id,
            'type' => $type,
            'started_at' => isset($data['started_at']) ? Carbon::parse($data['started_at']) : now(),
            'notes' => $data['notes'] ?? null,
            'location_lat' => $data['latitude'] ?? null,
            'location_lng' => $data['longitude'] ?? null,
        ]);

        AuditLog::log('updated', "Pausa de deslocamento iniciada ({$type}) (Origem: PWA Sync)", $workOrder);

        return true;
    }

    private function processComplaint(array $data): bool
    {
        $workOrderId = (int) ($data['work_order_id'] ?? 0);
        $workOrder = $this->findAuthorizedWorkOrderOrFail($workOrderId, 'update', 'Nao autorizado para registrar ocorrencia.');

        ServiceCall::create([
            'tenant_id' => $this->tenantId,
            'customer_id' => $workOrder->customer_id,
            'source' => 'pwa_offline',
            'source_id' => $workOrder->id,
            'observations' => ($data['subject'] ?? 'Ocorrência em campo').': '.($data['description'] ?? ''),
            'priority' => $data['priority'] ?? 'medium',
            'status' => ServiceCallStatus::PENDING_SCHEDULING,
            'created_by' => $this->user->id,
        ]);

        AuditLog::log('created', 'Ocorrência registrada via PWA Sync', $workOrder);

        return true;
    }

    private function processMaterialRequest(array $data): bool
    {
        $workOrderId = (int) ($data['work_order_id'] ?? 0);
        $workOrder = $this->findAuthorizedWorkOrderOrFail($workOrderId, 'update', 'Nao autorizado para solicitar material.');

        $tenantId = $this->tenantId;
        $lastRef = MaterialRequest::where('tenant_id', $tenantId)->max('id') ?? 0;
        $reference = 'MR-'.str_pad((string) ($lastRef + 1), 6, '0', STR_PAD_LEFT);

        $materialRequest = MaterialRequest::create([
            'tenant_id' => $tenantId,
            'work_order_id' => $workOrder->id,
            'requester_id' => $this->user->id,
            'reference' => $reference,
            'status' => 'pending',
            'priority' => $data['urgency'] ?? $data['priority'] ?? 'normal',
            'justification' => $data['notes'] ?? $data['justification'] ?? null,
            'warehouse_id' => $data['warehouse_id'] ?? null,
        ]);

        // Criar itens da solicitação se o model suportar
        if (method_exists($materialRequest, 'items') && ! empty($data['items'])) {
            foreach ($data['items'] as $item) {
                $materialRequest->items()->create([
                    'product_id' => $item['product_id'] ?? null,
                    'quantity' => $item['quantity'] ?? 1,
                    'name' => $item['name'] ?? '',
                ]);
            }
        }

        AuditLog::log('created', 'Solicitação de material via PWA Sync', $workOrder);

        return true;
    }

    private function photoDescription(string $entityType): string
    {
        return match ($entityType) {
            'before' => 'Foto antes',
            'after' => 'Foto depois',
            'checklist' => 'Foto de checklist',
            'expense' => 'Comprovante de despesa',
            default => 'Foto geral',
        };
    }

    public function pull(array $data)
    {

        $since = ($data['since'] ?? '1970-01-01T00:00:00Z');
        $sinceDate = Carbon::parse($since);
        $userId = $this->user->id;
        $tenantId = $this->tenantId;

        // Work orders: for admins show all, for technicians show only assigned
        /** @var User $currentUser */
        $currentUser = $this->user;
        $isAdmin = $currentUser->can('os.work_order.view');

        $workOrders = WorkOrder::where('tenant_id', $tenantId)
            ->where('updated_at', '>=', $sinceDate)
            ->when(! $isAdmin, function ($q) use ($userId) {
                $q->where(function ($sub) use ($userId) {
                    $sub->where('assigned_to', $userId)
                        ->orWhereHas('technicians', fn ($t) => $t->where('user_id', $userId));
                });
            })
            ->with([
                'customer:id,name,phone,email,address_street,address_number,address_city,latitude,longitude',
                'equipment:id',
                'displacementStops',
                'equipmentsList:id',
                'technicians:id',
                'items.product:id,name',
            ])
            ->get()
            ->map(function ($wo) {
                $status = $wo->status === WorkOrder::STATUS_PENDING
                    ? WorkOrder::STATUS_OPEN
                    : $wo->status;

                $equipmentIds = collect([$wo->equipment_id])
                    ->filter(fn (?int $id): bool => $id !== null)
                    ->merge($wo->equipmentsList?->pluck('id') ?? [])
                    ->map(fn (mixed $id): int => (int) $id)
                    ->unique()
                    ->values()
                    ->all();

                return [
                    'id' => $wo->id,
                    'number' => $wo->number,
                    'os_number' => $wo->os_number,
                    'assigned_to' => $wo->assigned_to,
                    'status' => $status,
                    'priority' => $wo->priority,
                    'checklist_id' => $wo->checklist_id,
                    'scheduled_date' => $wo->scheduled_date?->toISOString(),
                    'customer_id' => $wo->customer_id,
                    'customer_name' => $wo->customer?->name,
                    'customer_phone' => $wo->customer?->phone,
                    'contact_phone' => $wo->contact_phone,
                    'customer_address' => trim(implode(' ', array_filter([
                        $wo->customer?->address_street,
                        $wo->customer?->address_number,
                    ]))),
                    'city' => $wo->customer?->address_city,
                    'description' => $wo->description,
                    'sla_due_at' => $wo->sla_due_at?->toISOString(),
                    'latitude' => $wo->customer?->latitude,
                    'longitude' => $wo->customer?->longitude,
                    'google_maps_link' => $wo->google_maps_link,
                    'waze_link' => $wo->waze_link,
                    'equipment_ids' => $equipmentIds,
                    'technician_ids' => $wo->technicians?->pluck('id')->values()->all() ?? [],
                    'service_type_name' => $wo->service_type,
                    'service_type' => $wo->service_type,
                    'technical_report' => $wo->technical_report,
                    'internal_notes' => $wo->internal_notes,
                    'is_warranty' => (bool) $wo->is_warranty,
                    'total_amount' => $wo->total,
                    'displacement_value' => $wo->displacement_value,
                    'items' => $wo->items?->map(fn ($item) => [
                        'id' => $item->id,
                        'product_id' => $item->reference_id,
                        'product_name' => $item->product?->name,
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price,
                        'total' => $item->total,
                    ])->values()->all() ?? [],
                    'updated_at' => $wo->updated_at->toISOString(),
                    'displacement_started_at' => $wo->displacement_started_at?->toISOString(),
                    'displacement_arrived_at' => $wo->displacement_arrived_at?->toISOString(),
                    'displacement_duration_minutes' => $wo->displacement_duration_minutes,
                    'displacement_status' => $this->displacementStatus($wo),
                    'service_started_at' => $wo->service_started_at?->toISOString(),
                    'wait_time_minutes' => $wo->wait_time_minutes,
                    'service_duration_minutes' => $wo->service_duration_minutes,
                    'return_started_at' => $wo->return_started_at?->toISOString(),
                    'return_destination' => $wo->return_destination,
                    'return_arrived_at' => $wo->return_arrived_at?->toISOString(),
                    'return_duration_minutes' => $wo->return_duration_minutes,
                    'total_duration_minutes' => $wo->total_duration_minutes,
                    'completed_at' => $wo->completed_at?->toISOString(),
                    'displacement_stops' => ($wo->displacementStops ?? collect())->sortBy('started_at')->values()->map(fn ($s) => [
                        'id' => $s->id,
                        'type' => $s->type,
                        'started_at' => $s->started_at->toISOString(),
                        'ended_at' => $s->ended_at?->toISOString(),
                    ])->toArray(),
                ];
            });

        // Equipment linked to those work orders
        $woIds = $workOrders->pluck('id');
        $equipment = Equipment::where('tenant_id', $tenantId)
            ->whereIn('id', function ($q) use ($woIds) {
                $q->select('equipment_id')
                    ->from('work_order_equipments')
                    ->whereIn('work_order_id', $woIds);
            })
            ->where('updated_at', '>=', $sinceDate)
            ->select([
                'id', 'customer_id', 'type', 'brand', 'model',
                'serial_number', 'capacity', 'resolution', 'location', 'updated_at',
            ])
            ->get()
            ->map(fn ($e) => [
                ...$e->toArray(),
                'updated_at' => $e->updated_at->toISOString(),
            ]);

        // Active checklists
        $checklists = ServiceChecklist::where('tenant_id', $tenantId)
            ->where('updated_at', '>=', $sinceDate)
            ->where('is_active', true)
            ->with('items')
            ->get()
            ->map(fn ($cl) => [
                'id' => $cl->id,
                'name' => $cl->name,
                'service_type' => null,
                'items' => $cl->items->map(fn ($item) => [
                    'id' => $item->id,
                    'label' => $item->description,
                    'type' => $this->normalizeChecklistItemType((string) $item->type),
                    'required' => $item->is_required,
                    'options' => null,
                ])->toArray(),
                'updated_at' => $cl->updated_at->toISOString(),
            ]);

        // Standard weights
        $standardWeights = StandardWeight::where('tenant_id', $tenantId)
            ->where('updated_at', '>=', $sinceDate)
            ->select([
                'id', 'code', 'nominal_value', 'precision_class',
                'certificate_number', 'certificate_expiry', 'updated_at',
            ])
            ->get()
            ->map(fn ($sw) => [
                ...$sw->toArray(),
                'updated_at' => $sw->updated_at->toISOString(),
            ]);

        return ApiResponse::data([
            'work_orders' => $workOrders,
            'equipment' => $equipment,
            'checklists' => $checklists,
            'standard_weights' => $standardWeights,
            'updated_at' => now()->toISOString(),
        ]);
    }

    public function batchPush(array $data)
    {

        $processed = 0;
        $conflicts = [];
        $errors = [];

        DB::beginTransaction();

        try {
            foreach (($data['mutations'] ?? []) as $mutation) {
                try {
                    $applied = match ($mutation['type']) {
                        'checklist_response' => $this->processChecklistResponse($mutation['data'], $conflicts),
                        'expense' => $this->processExpense($mutation['data'], $conflicts),
                        'signature' => $this->processSignature($mutation['data'], $conflicts),
                        'status_change' => $this->processStatusChange($mutation['data'], $conflicts),
                        'displacement_start' => $this->processDisplacementStart($mutation['data'], $conflicts),
                        'displacement_arrive' => $this->processDisplacementArrive($mutation['data'], $conflicts),
                        'displacement_location' => $this->processDisplacementLocation($mutation['data'], $conflicts),
                        'displacement_stop' => $this->processDisplacementStop($mutation['data'], $conflicts),
                        'nps_response' => $this->processNpsResponse($mutation['data']),
                        'complaint' => $this->processComplaint($mutation['data']),
                        'work_order_create' => $this->processWorkOrderCreate($mutation['data']),
                        'material_request' => $this->processMaterialRequest($mutation['data']),
                        'feedback' => $this->processFeedback($mutation['data']),
                        'seal_application' => $this->processSealApplication($mutation['data'], $conflicts),
                        default => false,
                    };
                    if ($applied) {
                        $processed++;
                    }
                } catch (\Exception $e) {
                    $errors[] = [
                        'type' => $mutation['type'],
                        'id' => $mutation['data']['id'] ?? 'unknown',
                        'message' => $e->getMessage(),
                    ];
                    Log::warning('[TechSync] Mutation failed', [
                        'type' => $mutation['type'],
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[TechSync] Batch push failed', ['error' => $e->getMessage()]);

            return ApiResponse::data([
                'processed' => 0,
                'conflicts' => [],
                'errors' => [['type' => 'batch', 'id' => 'all', 'message' => $e->getMessage()]],
            ], 500, ['message' => 'Falha na sincronização.']);
        }

        // Persistir conflitos para auditoria
        foreach ($conflicts as $conflict) {
            try {
                SyncConflictLog::create([
                    'tenant_id' => $this->tenantId,
                    'user_id' => $this->user->id,
                    'work_order_id' => $conflict['work_order_id'] ?? $conflict['id'] ?? 0,
                    'conflict_type' => $conflict['type'] ?? 'unknown',
                    'client_data' => $conflict['client_data'] ?? $conflict,
                    'server_data' => ['server_updated_at' => $conflict['server_updated_at'] ?? null],
                ]);
            } catch (\Throwable $e) {
                Log::warning('SyncConflictLog: falha ao persistir conflito', ['error' => $e->getMessage()]);
            }
        }

        return ApiResponse::data([
            'processed' => $processed,
            'conflicts' => $conflicts,
            'errors' => $errors,
        ]);
    }

    public function uploadPhoto(array $data)
    {

        $workOrder = $this->findAuthorizedWorkOrderOrFail(
            (int) ($data['work_order_id'] ?? 0),
            'update',
            'Nao autorizado para anexar foto nesta OS.'
        );

        $file = ($data['file'] ?? null);
        $path = ($data['file'] ?? null)->store(
            "work-orders/{$data['work_order_id']}/photos",
            'public'
        );

        $attachment = WorkOrderAttachment::create([
            'tenant_id' => $this->tenantId,
            'work_order_id' => $workOrder->id,
            'uploaded_by' => $this->user->id,
            'file_name' => FilenameSanitizer::sanitize($file->getClientOriginalName()),
            'file_path' => $path,
            'file_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'description' => $this->photoDescription((string) ($data['entity_type'] ?? '')),
        ]);

        AuditLog::log(
            'created',
            "Foto/Anexo adicionado: {$attachment->file_name} (Origem: PWA Sync)",
            $workOrder
        );

        return ApiResponse::data([
            'id' => $attachment->id,
            'path' => $path,
            'file_path' => $path,
            'url' => Storage::disk('public')->url($path),
        ], 201);
    }

    private function findAuthorizedWorkOrderOrFail(
        int $workOrderId,
        string $ability = 'view',
        string $message = 'Nao autorizado.'
    ): WorkOrder {
        $workOrder = WorkOrder::query()
            ->where('tenant_id', $this->tenantId)
            ->findOrFail($workOrderId);

        $user = $this->user;

        if (! $user || ! $user->can($ability, $workOrder)) {
            throw new AuthorizationException($message);
        }

        return $workOrder;
    }

    /* ─── Private processors ─────────────────────────────── */

    /**
     * @param  SyncPayload  $data
     * @param  array<int, SyncConflict>  $conflicts
     */
    private function processExpense(array $data, array &$conflicts): bool
    {
        $tenantId = $this->tenantId;
        $workOrderId = isset($data['work_order_id']) ? (int) $data['work_order_id'] : null;

        $workOrder = null;
        if ($workOrderId) {
            $workOrder = WorkOrder::query()
                ->where('tenant_id', $tenantId)
                ->findOrFail($workOrderId);

            if (! $workOrder->isTechnicianAuthorized($this->user->id)) {
                throw new HttpException(403, 'Não autorizado a sincronizar despesa desta OS.');
            }
        }

        $categoryId = isset($data['expense_category_id']) ? (int) $data['expense_category_id'] : null;
        if ($categoryId) {
            $category = ExpenseCategory::query()
                ->where('tenant_id', $tenantId)
                ->where('id', $categoryId)
                ->first();

            if (! $category) {
                throw new \InvalidArgumentException('Categoria de despesa inválida para sincronização offline.');
            }
        } else {
            $category = null;
        }

        $affectsTechnicianCash = array_key_exists('affects_technician_cash', $data) && $data['affects_technician_cash'] !== null
            ? (bool) $data['affects_technician_cash']
            : ($category instanceof ExpenseCategory ? (bool) $category->default_affects_technician_cash : false);

        $affectsNetValue = array_key_exists('affects_net_value', $data) && $data['affects_net_value'] !== null
            ? (bool) $data['affects_net_value']
            : ($category instanceof ExpenseCategory ? (bool) $category->default_affects_net_value : false);

        $expense = Expense::create([
            'tenant_id' => $tenantId,
            'expense_category_id' => $categoryId,
            'work_order_id' => $workOrder?->id,
            'description' => (string) ($data['description'] ?? 'Despesa offline'),
            'amount' => $data['amount'] ?? 0,
            'expense_date' => $data['expense_date'] ?? now()->toDateString(),
            'payment_method' => $data['payment_method'] ?? null,
            'notes' => $data['notes'] ?? null,
            'affects_technician_cash' => $affectsTechnicianCash,
            'affects_net_value' => $affectsNetValue,
            'created_by' => $this->user->id,
            'status' => ExpenseStatus::PENDING,
        ]);

        ExpenseStatusHistory::create([
            'expense_id' => $expense->id,
            'changed_by' => $this->user->id,
            'from_status' => null,
            'to_status' => ExpenseStatus::PENDING->value,
            'reason' => 'Despesa sincronizada do modo offline',
        ]);

        if ($workOrder) {
            AuditLog::log(
                'created',
                "Despesa de {$expense->amount} adicionada na OS (Origem: PWA Sync)",
                $workOrder
            );
        }

        return true;
    }

    /**
     * @param  SyncPayload  $data
     * @param  array<int, SyncConflict>  $conflicts
     */
    private function processSignature(array $data, array &$conflicts): bool
    {
        $workOrder = $this->findAuthorizedWorkOrderOrFail(
            (int) $data['work_order_id'],
            'update',
            'Nao autorizado a sincronizar assinatura desta OS.'
        );

        if (! in_array($workOrder->status, [
            WorkOrder::STATUS_AWAITING_RETURN,
            WorkOrder::STATUS_IN_RETURN,
            WorkOrder::STATUS_RETURN_PAUSED,
            WorkOrder::STATUS_COMPLETED,
            WorkOrder::STATUS_DELIVERED,
        ], true)) {
            throw new \InvalidArgumentException('Assinatura so pode ser sincronizada apos a conclusao do servico.');
        }

        // Decode base64 PNG and store
        $signaturePayload = $data['png_base64'] ?? '';
        if (! is_string($signaturePayload) || $signaturePayload === '') {
            throw new \InvalidArgumentException('Assinatura offline invalida.');
        }

        $pngData = base64_decode($signaturePayload, true);
        if ($pngData === false) {
            throw new \InvalidArgumentException('Assinatura offline invalida.');
        }

        $previousSignaturePath = $workOrder->signature_path;
        $path = "work-orders/{$data['work_order_id']}/signature.png";
        Storage::disk('public')->put($path, $pngData);

        $signedAt = isset($data['captured_at']) ? Carbon::parse($data['captured_at']) : now();

        DB::transaction(function () use ($data, $path, $signedAt, $signaturePayload, $workOrder, $previousSignaturePath) {
            $workOrder->update([
                'signature_path' => $path,
                'signature_signer' => $data['signer_name'] ?? null,
                'signature_at' => $signedAt,
                'signature_ip' => request()->ip(),
            ]);

            WorkOrderSignature::create([
                'tenant_id' => $workOrder->tenant_id,
                'signer_document' => $data['signer_document'] ?? null,
                'work_order_id' => $workOrder->id,
                'signer_name' => $data['signer_name'] ?? 'Cliente',
                'signer_type' => 'customer',
                'signature_data' => $signaturePayload,
                'signed_at' => $signedAt,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            AuditLog::log(
                'updated',
                "OS {$workOrder->business_number}: assinatura sincronizada (Origem: PWA Sync)",
                $workOrder,
                ['signature_path' => $previousSignaturePath],
                ['signature_path' => $path, 'signature_signer' => $data['signer_name'] ?? null]
            );
        });

        return true;
    }

    /**
     * @param  SyncPayload  $data
     * @param  array<int, SyncConflict>  $conflicts
     */
    private function processDisplacementStart(array $data, array &$conflicts): bool
    {
        $workOrder = $this->findAuthorizedWorkOrderOrFail((int) $data['work_order_id'], 'changeStatus');
        if ($workOrder->displacement_started_at) {
            return false;
        }
        $workOrder->update([
            'displacement_started_at' => now(),
            'status' => WorkOrder::STATUS_IN_DISPLACEMENT,
        ]);
        if (! empty($data['latitude']) && ! empty($data['longitude'])) {
            WorkOrderDisplacementLocation::create([
                'work_order_id' => $workOrder->id,
                'user_id' => $this->user->id,
                'latitude' => $data['latitude'],
                'longitude' => $data['longitude'],
                'recorded_at' => now(),
            ]);
        }

        AuditLog::log(
            'updated',
            'Deslocamento iniciado (Origem: PWA Sync)',
            $workOrder
        );

        return true;
    }

    /**
     * @param  SyncPayload  $data
     * @param  array<int, SyncConflict>  $conflicts
     */
    private function processDisplacementArrive(array $data, array &$conflicts): bool
    {
        $workOrder = $this->findAuthorizedWorkOrderOrFail((int) $data['work_order_id'], 'changeStatus');
        if (! $workOrder->displacement_started_at || $workOrder->displacement_arrived_at) {
            return false;
        }
        $workOrder->update([
            'displacement_arrived_at' => now(),
            'status' => WorkOrder::STATUS_AT_CLIENT,
        ]);
        if (! empty($data['latitude']) && ! empty($data['longitude'])) {
            WorkOrderDisplacementLocation::create([
                'work_order_id' => $workOrder->id,
                'user_id' => $this->user->id,
                'latitude' => $data['latitude'],
                'longitude' => $data['longitude'],
                'recorded_at' => now(),
            ]);
        }
        $this->recalculateDisplacementDuration($workOrder);

        AuditLog::log(
            'updated',
            'Chegada ao local/cliente registrada (Origem: PWA Sync)',
            $workOrder
        );

        return true;
    }

    /**
     * @param  SyncPayload  $data
     * @param  array<int, SyncConflict>  $conflicts
     */
    private function processDisplacementLocation(array $data, array &$conflicts): bool
    {
        $workOrder = $this->findAuthorizedWorkOrderOrFail((int) $data['work_order_id'], 'changeStatus');
        if (! $workOrder->displacement_started_at || $workOrder->displacement_arrived_at) {
            return false;
        }
        if (empty($data['latitude']) || empty($data['longitude'])) {
            return false;
        }
        WorkOrderDisplacementLocation::create([
            'work_order_id' => $workOrder->id,
            'user_id' => $this->user->id,
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'recorded_at' => isset($data['recorded_at']) ? Carbon::parse($data['recorded_at']) : now(),
        ]);

        return true;
    }

    private function recalculateDisplacementDuration(WorkOrder $workOrder): void
    {
        if (! $workOrder->displacement_started_at || ! $workOrder->displacement_arrived_at) {
            return;
        }
        $start = Carbon::parse($workOrder->displacement_started_at);
        $arrived = Carbon::parse($workOrder->displacement_arrived_at);
        $grossMinutes = (int) $start->diffInMinutes($arrived);
        $stopMinutes = $workOrder->displacementStops()
            ->whereNotNull('ended_at')
            ->get()
            ->sum(fn ($s) => $s->duration_minutes ?? 0);
        $effectiveMinutes = max(0, $grossMinutes - $stopMinutes);
        $workOrder->update(['displacement_duration_minutes' => $effectiveMinutes]);
    }

    private function displacementStatus(WorkOrder $wo): string
    {
        if (! $wo->displacement_started_at) {
            return 'not_started';
        }

        if ($wo->status === WorkOrder::STATUS_DISPLACEMENT_PAUSED) {
            return 'paused';
        }

        if ($wo->displacement_arrived_at) {
            return 'arrived';
        }

        return 'in_progress';
    }

    private function normalizeChecklistItemType(string $type): string
    {
        return match ($type) {
            'check' => 'boolean',
            default => $type,
        };
    }

    private function normalizeChecklistResponseValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $encoded === false ? null : $encoded;
    }

    /* ─── New offline mutation processors ────────────────── */

    /**
     * @param  SyncPayload  $data
     */
    private function processNpsResponse(array $data): bool
    {
        $workOrderId = (int) ($data['work_order_id'] ?? 0);
        $workOrder = $this->findAuthorizedWorkOrderOrFail($workOrderId, 'view', 'Nao autorizado para NPS desta OS.');

        NpsSurvey::create([
            'tenant_id' => $this->tenantId,
            'work_order_id' => $workOrder->id,
            'customer_id' => $workOrder->customer_id,
            'score' => (int) ($data['score'] ?? 0),
            'feedback' => $data['comment'] ?? $data['feedback'] ?? null,
            'category' => $data['category'] ?? 'field',
            'responded_at' => $data['answered_at'] ?? $data['responded_at'] ?? now(),
        ]);

        AuditLog::log('created', "NPS (nota {$data['score']}) registrado via PWA Sync", $workOrder);

        return true;
    }

    /**
     * @param  SyncPayload  $data
     */
    private function processWorkOrderCreate(array $data): bool
    {
        $tenantId = $this->tenantId;
        $user = $this->user;

        $lastNumber = WorkOrder::where('tenant_id', $tenantId)->max('id') ?? 0;
        $osNumber = 'OS-'.str_pad((string) ($lastNumber + 1), 6, '0', STR_PAD_LEFT);

        $workOrder = WorkOrder::create([
            'tenant_id' => $tenantId,
            'customer_id' => $data['customer_id'] ?? null,
            'assigned_to' => $user->id,
            'number' => $osNumber,
            'os_number' => $osNumber,
            'description' => $data['description'] ?? $data['title'] ?? 'OS criada em campo',
            'priority' => $data['priority'] ?? 'normal',
            'status' => WorkOrder::STATUS_PENDING,
            'origin_type' => 'pwa_offline',
            'scheduled_date' => $data['scheduled_date'] ?? now()->toDateString(),
            'created_by' => $user->id,
            'displacement_value' => 0,
            'total' => 0,
        ]);

        AuditLog::log('created', "OS #{$workOrder->id} criada via PWA Sync (offline)", $workOrder);

        return true;
    }

    /**
     * @param  SyncPayload  $data
     */
    private function processFeedback(array $data): bool
    {
        $workOrderId = (int) ($data['work_order_id'] ?? 0);

        if ($workOrderId > 0) {
            $workOrder = $this->findAuthorizedWorkOrderOrFail($workOrderId, 'view', 'Nao autorizado.');
        }

        TechnicianFeedback::updateOrCreate(
            [
                'tenant_id' => $this->tenantId,
                'user_id' => $this->user->id,
                'date' => $data['date'] ?? now()->toDateString(),
            ],
            [
                'work_order_id' => $workOrderId > 0 ? $workOrderId : null,
                'type' => $data['type'] ?? 'general',
                'message' => $data['message'] ?? '',
                'rating' => $data['rating'] ?? null,
            ]
        );

        return true;
    }

    /**
     * @param  SyncPayload  $data
     * @param  array<int, SyncConflict>  $conflicts
     */
    private function processSealApplication(array $data, array &$conflicts): bool
    {
        $workOrderId = (int) ($data['work_order_id'] ?? 0);
        $workOrder = $this->findAuthorizedWorkOrderOrFail($workOrderId, 'update', 'Nao autorizado para aplicar lacres.');

        // Conflict check
        if (isset($data['client_work_order_updated_at'])) {
            $clientUpdated = Carbon::parse($data['client_work_order_updated_at']);
            if ($workOrder->updated_at->gt($clientUpdated)) {
                $conflicts[] = [
                    'type' => 'seal_application',
                    'id' => $data['id'] ?? 'unknown',
                    'server_updated_at' => $workOrder->updated_at->toISOString(),
                ];

                return false;
            }
        }

        $sealItems = $data['seals'] ?? [];
        if (! is_array($sealItems)) {
            $sealItems = [];
        }
        $sealItems = array_values(array_filter($sealItems, 'is_array'));

        /** @var array<int, array<string, mixed>> $sealItems */
        $seals = collect($sealItems);
        foreach ($seals as $seal) {
            SealApplication::create([
                'tenant_id' => $this->tenantId,
                'work_order_id' => $workOrder->id,
                'equipment_id' => $seal['equipment_id'] ?? $data['equipment_id'] ?? null,
                'seal_number' => $seal['seal_number'] ?? $seal['number'] ?? '',
                'location' => $seal['location'] ?? null,
                'applied_by' => $this->user->id,
                'applied_at' => $seal['applied_at'] ?? now(),
            ]);
        }

        AuditLog::log('created', "Lacre(s) aplicado(s) via PWA Sync ({$seals->count()} itens)", $workOrder);

        return true;
    }
}
