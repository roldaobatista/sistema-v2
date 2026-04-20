<?php

namespace App\Services;

use App\Events\WorkOrderCompleted;
use App\Events\WorkOrderInvoiced;
use App\Events\WorkOrderStarted;
use App\Models\AccountReceivable;
use App\Models\AuditLog;
use App\Models\CommissionEvent;
use App\Models\Equipment;
use App\Models\Notification;
use App\Models\Product;
use App\Models\Role;
use App\Models\Service;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderItem;
use App\Support\SearchSanitizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class WorkOrderService
{
    /**
     * Valida referência de produto/serviço no tenant.
     */
    public function validateItemReference(string $type, ?int $referenceId, int $tenantId): ?string
    {
        if (! $referenceId) {
            return null;
        }

        $exists = match ($type) {
            'product' => Product::query()->where('tenant_id', $tenantId)->where('id', $referenceId)->exists(),
            'service' => Service::query()->where('tenant_id', $tenantId)->where('id', $referenceId)->exists(),
            default => false,
        };

        if ($exists) {
            return null;
        }

        $label = $type === 'product' ? 'produto' : 'serviço';

        return "Referência de {$label} inválida para este tenant.";
    }

    /**
     * Processa listagem com filtros.
     */
    public function list(array $filters, int $tenantId, ?int $scopedUserId = null)
    {
        $query = WorkOrder::with([
            'customer:id,name,phone',
            'assignee:id,name',
            'equipment:id,type,brand,model',
            'seller:id,name',
            'quote:id,quote_number',
            'serviceCall',
            'branch:id,name',
            'technicians:id,name',
        ]);

        $query->where('tenant_id', $tenantId);

        if ($scopedUserId) {
            $query->where(function ($q) use ($scopedUserId) {
                $q->where('assigned_to', $scopedUserId)
                    ->orWhere('created_by', $scopedUserId)
                    ->orWhereHas('technicians', fn ($technicians) => $technicians->where('user_id', $scopedUserId));
            });
        }

        if (! empty($filters['search'])) {
            $search = SearchSanitizer::escapeLike($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('number', 'like', "%{$search}%")
                    ->orWhere('os_number', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$search}%"));
            });
        }

        if (! empty($filters['status'])) {
            if (str_contains($filters['status'], ',')) {
                $query->whereIn('status', explode(',', $filters['status']));
            } else {
                $query->where('status', $filters['status']);
            }
        }

        if (! empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        if (! empty($filters['assigned_to'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('assigned_to', $filters['assigned_to'])
                    ->orWhereHas('technicians', fn ($t) => $t->where('user_id', $filters['assigned_to']));
            });
        }

        if (! empty($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        if (! empty($filters['recurring_contract_id'])) {
            $query->where('recurring_contract_id', $filters['recurring_contract_id']);
        }

        if (! empty($filters['equipment_id'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('equipment_id', $filters['equipment_id'])
                    ->orWhereHas('equipmentsList', fn ($e) => $e->where('equipment_id', $filters['equipment_id']));
            });
        }

        if (! empty($filters['origin_type'])) {
            $query->where('origin_type', $filters['origin_type']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }
        if (! empty($filters['has_schedule']) && filter_var($filters['has_schedule'], FILTER_VALIDATE_BOOLEAN)) {
            $query->whereNotNull('scheduled_date');
        }
        if (! empty($filters['scheduled_from'])) {
            $query->whereDate('scheduled_date', '>=', $filters['scheduled_from']);
        }
        if (! empty($filters['scheduled_to'])) {
            $query->whereDate('scheduled_date', '<=', $filters['scheduled_to']);
        }

        if (! empty($filters['pending_invoice']) && filter_var($filters['pending_invoice'], FILTER_VALIDATE_BOOLEAN)) {
            $query->whereIn('status', [WorkOrder::STATUS_COMPLETED, WorkOrder::STATUS_DELIVERED])
                ->whereNotExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('invoices')
                        ->whereColumn('invoices.work_order_id', 'work_orders.id');
                });
        }

        $statusCounts = (clone $query)->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        if (! empty($filters['pending_invoice']) && filter_var($filters['pending_invoice'], FILTER_VALIDATE_BOOLEAN)) {
            $orders = $query->orderByRaw('COALESCE(delivered_at, completed_at) ASC')
                ->paginate(min((int) ($filters['per_page'] ?? 20), 100));
        } else {
            $orders = $query->orderByDesc('created_at')
                ->paginate(min((int) ($filters['per_page'] ?? 20), 100));
        }

        $orders->statusCounts = $statusCounts;

        return $orders;
    }

    /**
     * Cria uma nova OS.
     */
    public function create(array $validated, User $user, int $tenantId): array
    {
        $hasDiscount = (float) ($validated['discount'] ?? 0) > 0
            || (float) ($validated['discount_percentage'] ?? 0) > 0;

        if ($hasDiscount && ! $user->can('os.work_order.apply_discount')) {
            throw ValidationException::withMessages(['discount' => 'Apenas gerentes/admin podem aplicar descontos.']);
        }

        if (isset($validated['discount_percentage']) && (float) $validated['discount_percentage'] > 0) {
            $validated['discount'] = 0;
        } elseif (isset($validated['discount']) && (float) $validated['discount'] > 0) {
            $validated['discount_percentage'] = 0;
        } else {
            $validated['discount'] = 0;
            $validated['discount_percentage'] = 0;
        }

        if (! empty($validated['items'])) {
            foreach ($validated['items'] as $index => $item) {
                $message = $this->validateItemReference(
                    (string) $item['type'],
                    isset($item['reference_id']) ? (int) $item['reference_id'] : null,
                    $tenantId
                );

                if ($message) {
                    throw ValidationException::withMessages(["items.{$index}.reference_id" => $message]);
                }
            }
        }

        $initialStatus = $validated['initial_status'] ?? WorkOrder::STATUS_OPEN;

        $order = DB::transaction(function () use ($validated, $tenantId, $user, $initialStatus) {
            if (! empty($validated['new_equipment'])) {
                $equip = Equipment::create([
                    ...$validated['new_equipment'],
                    'tenant_id' => $tenantId,
                    'customer_id' => $validated['customer_id'],
                ]);
                $validated['equipment_id'] = $equip->id;
            }

            $orderData = collect($validated)->except(['items', 'new_equipment', 'technician_ids', 'equipment_ids', 'initial_status'])->toArray();

            if ($initialStatus !== WorkOrder::STATUS_OPEN) {
                $orderData['started_at'] = $orderData['started_at'] ?? ($orderData['received_at'] ?? now());
                if (in_array($initialStatus, [WorkOrder::STATUS_COMPLETED, WorkOrder::STATUS_DELIVERED, WorkOrder::STATUS_INVOICED])) {
                    $orderData['completed_at'] = $orderData['completed_at'] ?? now();
                }
                if (in_array($initialStatus, [WorkOrder::STATUS_DELIVERED, WorkOrder::STATUS_INVOICED])) {
                    $orderData['delivered_at'] = $orderData['delivered_at'] ?? now();
                }
            }

            $order = WorkOrder::create([
                ...$orderData,
                'number' => WorkOrder::nextNumber($tenantId),
                'tenant_id' => $tenantId,
                'created_by' => $user->id,
                'status' => $initialStatus,
            ]);

            if (! empty($validated['technician_ids'])) {
                foreach ($validated['technician_ids'] as $techId) {
                    $order->technicians()->attach($techId, [
                        'role' => Role::TECNICO,
                        'tenant_id' => $order->tenant_id,
                    ]);
                }
            }
            if (! empty($validated['driver_id'])) {
                $order->technicians()->syncWithoutDetaching([
                    $validated['driver_id'] => [
                        'role' => Role::MOTORISTA,
                        'tenant_id' => $order->tenant_id,
                    ],
                ]);

                try {
                    Notification::notify(
                        $order->tenant_id,
                        $validated['driver_id'],
                        'driver_assigned',
                        'Atribuído como motorista',
                        [
                            'message' => "Você foi atribuído como motorista na OS {$order->business_number}.",
                            'icon' => 'truck',
                            'color' => 'info',
                            'data' => ['work_order_id' => $order->id],
                        ]
                    );
                } catch (\Throwable $e) {
                    Log::warning('Falha ao notificar driver', ['error' => $e->getMessage()]);
                }
            }

            if (! empty($validated['equipment_ids'])) {
                $order->equipmentsList()->attach($validated['equipment_ids']);
            }

            if (! empty($validated['items'])) {
                foreach ($validated['items'] as $item) {
                    $order->items()->create($item);
                }
            }

            $order->statusHistory()->create([
                'tenant_id' => $tenantId,
                'user_id' => $user->id,
                'from_status' => null,
                'to_status' => $initialStatus,
                'notes' => $initialStatus !== WorkOrder::STATUS_OPEN ? 'OS criada (lançamento retroativo)' : 'OS criada',
            ]);

            return $order;
        });

        if ($initialStatus !== WorkOrder::STATUS_OPEN) {
            try {
                if (in_array($initialStatus, [WorkOrder::STATUS_IN_PROGRESS, WorkOrder::STATUS_IN_DISPLACEMENT, WorkOrder::STATUS_IN_SERVICE])) {
                    WorkOrderStarted::dispatch($order, $user, WorkOrder::STATUS_OPEN);
                }

                if (in_array($initialStatus, [WorkOrder::STATUS_COMPLETED, WorkOrder::STATUS_DELIVERED, WorkOrder::STATUS_INVOICED])) {
                    WorkOrderCompleted::dispatch($order, $user, WorkOrder::STATUS_IN_PROGRESS);
                }

                if ($initialStatus === WorkOrder::STATUS_INVOICED) {
                    WorkOrderInvoiced::dispatch($order, $user, WorkOrder::STATUS_DELIVERED);
                }
            } catch (\Exception $eventEx) {
                Log::warning('Retroactive event dispatch failed', [
                    'work_order_id' => $order->id,
                    'error' => $eventEx->getMessage(),
                ]);
            }
        }

        $warrantyWarning = null;
        if ($order->equipment_id) {
            $equip = Equipment::find($order->equipment_id);
            if ($equip && $equip->warranty_expires_at && ! $equip->warranty_expires_at->isPast()) {
                $warrantyWarning = "Equipamento {$equip->code} está em garantia até {$equip->warranty_expires_at->format('d/m/Y')}. Verificar cobertura antes de faturar.";
            }
        }

        $order->load(['customer', 'equipment', 'assignee:id,name', 'seller:id,name', 'technicians', 'equipmentsList', 'items', 'statusHistory.user:id,name']);

        return ['order' => $order, 'warranty_warning' => $warrantyWarning];
    }

    /**
     * Atualiza uma OS existente.
     */
    public function update(array $validated, WorkOrder $workOrder, User $user): WorkOrder
    {
        if (in_array($workOrder->status, [WorkOrder::STATUS_INVOICED])) {
            throw ValidationException::withMessages(['status' => 'OS faturada não pode ser editada. Cancele o faturamento primeiro.']);
        }

        $hasDiscount = (float) ($validated['discount'] ?? 0) > 0
            || (float) ($validated['discount_percentage'] ?? 0) > 0;
        if ($hasDiscount && ! $user->can('os.work_order.apply_discount')) {
            throw ValidationException::withMessages(['discount' => 'Apenas gerentes/admin podem aplicar descontos.']);
        }

        if (array_key_exists('discount_percentage', $validated) || array_key_exists('discount', $validated)) {
            if (isset($validated['discount_percentage']) && (float) $validated['discount_percentage'] > 0) {
                $validated['discount'] = 0;
            } elseif (isset($validated['discount']) && (float) $validated['discount'] > 0) {
                $validated['discount_percentage'] = 0;
            } else {
                $validated['discount'] = 0;
                $validated['discount_percentage'] = 0;
            }
        }

        DB::beginTransaction();

        try {
            $technicianIds = $validated['technician_ids'] ?? null;
            $equipmentIds = $validated['equipment_ids'] ?? null;
            unset($validated['technician_ids'], $validated['equipment_ids']);

            $workOrder->update($validated);

            if ($technicianIds !== null) {
                $syncData = [];
                foreach ($technicianIds as $techId) {
                    $syncData[$techId] = [
                        'role' => Role::TECNICO,
                        'tenant_id' => $workOrder->tenant_id,
                    ];
                }
                if (! isset($validated['driver_id']) && $workOrder->driver_id) {
                    $syncData[$workOrder->driver_id] = [
                        'role' => Role::MOTORISTA,
                        'tenant_id' => $workOrder->tenant_id,
                    ];
                }
                $workOrder->technicians()->sync($syncData);
            }

            if (isset($validated['driver_id'])) {
                $driverId = $validated['driver_id'];
                $oldDriverId = $workOrder->getOriginal('driver_id');

                $workOrder->technicians()->wherePivot('role', Role::MOTORISTA)->detach();
                if ($driverId) {
                    $workOrder->technicians()->syncWithoutDetaching([
                        $driverId => [
                            'role' => Role::MOTORISTA,
                            'tenant_id' => $workOrder->tenant_id,
                        ],
                    ]);

                    if ($driverId != $oldDriverId) {
                        try {
                            Notification::notify(
                                $workOrder->tenant_id,
                                $driverId,
                                'driver_assigned',
                                'Atribuído como motorista',
                                [
                                    'message' => "Você foi atribuído como motorista na OS {$workOrder->business_number}.",
                                    'icon' => 'truck',
                                    'color' => 'info',
                                    'data' => ['work_order_id' => $workOrder->id],
                                ]
                            );
                        } catch (\Throwable $e) {
                            Log::warning('Falha ao notificar driver', ['error' => $e->getMessage()]);
                        }
                    }
                }
            }

            if ($equipmentIds !== null) {
                $workOrder->equipmentsList()->sync($equipmentIds);
            }

            if (isset($validated['discount']) || isset($validated['discount_percentage']) || isset($validated['displacement_value'])) {
                $workOrder->recalculateTotal();
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('WorkOrder update failed', ['id' => $workOrder->id, 'error' => $e->getMessage()]);

            throw $e;
        }

        return $workOrder->fresh()->load([
            'customer', 'equipment', 'assignee:id,name', 'seller:id,name',
            'technicians', 'equipmentsList', 'items', 'statusHistory.user:id,name',
        ]);
    }

    /**
     * Exclui uma OS e seus anexos físicos.
     */
    public function delete(WorkOrder $workOrder): void
    {
        if (in_array($workOrder->status, [WorkOrder::STATUS_COMPLETED, WorkOrder::STATUS_DELIVERED, WorkOrder::STATUS_INVOICED])) {
            throw ValidationException::withMessages(['status' => 'Não é possível excluir OS concluída, entregue ou faturada']);
        }

        $hasPayments = AccountReceivable::where('work_order_id', $workOrder->id)->exists();
        $hasCommissions = CommissionEvent::where('work_order_id', $workOrder->id)->exists();

        if ($hasPayments || $hasCommissions) {
            $blocks = [];
            if ($hasPayments) {
                $blocks[] = 'títulos financeiros';
            }
            if ($hasCommissions) {
                $blocks[] = 'comissões';
            }

            throw ValidationException::withMessages(['conflicts' => 'Não é possível excluir esta OS — possui '.implode(' e ', $blocks).' vinculados']);
        }

        DB::beginTransaction();

        try {
            $attachmentPaths = $workOrder->attachments()->pluck('file_path')->filter()->all();
            $signaturePath = $workOrder->signature_path;

            $workOrder->delete();

            DB::commit();

            foreach ($attachmentPaths as $attachmentPath) {
                Storage::disk('public')->delete($attachmentPath);
            }

            if ($signaturePath) {
                Storage::disk('public')->delete($signaturePath);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('WorkOrder delete failed', ['id' => $workOrder->id, 'error' => $e->getMessage()]);

            throw $e;
        }
    }

    public function addItem(array $validated, WorkOrder $workOrder, int $tenantId): WorkOrderItem
    {
        $message = $this->validateItemReference(
            $validated['type'],
            isset($validated['reference_id']) ? (int) $validated['reference_id'] : null,
            $tenantId
        );

        if ($message) {
            throw ValidationException::withMessages(['reference_id' => $message]);
        }

        DB::beginTransaction();

        try {
            $item = $workOrder->items()->create($validated);
            DB::commit();

            AuditLog::log('item_added', "Item adicionado à OS {$workOrder->business_number}", $workOrder, [], $item->toArray());

            return $item;
        } catch (\Exception $e) {
            DB::rollBack();

            throw $e;
        }
    }

    public function updateItem(array $validated, WorkOrder $workOrder, WorkOrderItem $item, int $tenantId): WorkOrderItem
    {
        if ($item->work_order_id !== $workOrder->id) {
            throw new \Exception('Item não pertence a esta OS', 403);
        }

        $type = $validated['type'] ?? $item->type;
        $refId = array_key_exists('reference_id', $validated) ? $validated['reference_id'] : $item->reference_id;

        $message = $this->validateItemReference($type, $refId ? (int) $refId : null, $tenantId);
        if ($message) {
            throw ValidationException::withMessages(['reference_id' => $message]);
        }

        DB::beginTransaction();

        try {
            $oldValues = $item->toArray();
            $item->update($validated);
            DB::commit();

            AuditLog::log('item_updated', "Item atualizado na OS {$workOrder->business_number}", $workOrder, $oldValues, $item->fresh()->toArray());

            return $item;
        } catch (\Exception $e) {
            DB::rollBack();

            throw $e;
        }
    }

    public function deleteItem(WorkOrder $workOrder, WorkOrderItem $item): void
    {
        if ($item->work_order_id !== $workOrder->id) {
            throw new \Exception('Item não pertence a esta OS', 403);
        }

        DB::beginTransaction();

        try {
            $itemData = $item->toArray();
            $item->delete();
            DB::commit();

            AuditLog::log('item_removed', "Item removido da OS {$workOrder->business_number}", $workOrder, $itemData, []);
        } catch (\Exception $e) {
            DB::rollBack();

            throw $e;
        }
    }
}
