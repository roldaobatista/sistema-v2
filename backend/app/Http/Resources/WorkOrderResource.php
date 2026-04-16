<?php

namespace App\Http\Resources;

use App\Enums\WorkOrderStatus;
use App\Models\ClientPortalUser;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WorkOrder
 */
class WorkOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if ($this->isExternalPortalRequest($request)) {
            return [
                'id' => $this->id,
                'os_number' => $this->os_number,
                'number' => $this->number,
                'status' => $this->status,
                'priority' => $this->priority,
                'description' => $this->description,
                'scheduled_date' => $this->scheduled_date?->toIso8601String(),
                'received_at' => $this->received_at?->toIso8601String(),
                'started_at' => $this->started_at?->toIso8601String(),
                'completed_at' => $this->completed_at?->toIso8601String(),
                'delivered_at' => $this->delivered_at?->toIso8601String(),
                'total' => $this->total,
                'signature_signer' => $this->signature_signer,
                'signature_at' => $this->signature_at?->toIso8601String(),
                'delivery_forecast' => $this->delivery_forecast?->toDateString(),
                'tags' => $this->tags ?? [],
                'photo_checklist' => $this->photo_checklist,
                'service_type' => $this->service_type,
                'warranty_until' => $this->warranty_until?->toIso8601String(),
                'is_under_warranty' => $this->is_under_warranty,
                'created_at' => $this->created_at?->toIso8601String(),
                'updated_at' => $this->updated_at?->toIso8601String(),
                'address' => $this->address,
                'city' => $this->city,
                'state' => $this->state,
                'zip_code' => $this->zip_code,
                'contact_phone' => $this->contact_phone,
                'customer' => $this->when($this->relationLoaded('customer'), fn () => [
                    'name' => $this->customer?->name,
                    'latitude' => $this->safeRelatedAttribute($this->customer, 'latitude'),
                    'longitude' => $this->safeRelatedAttribute($this->customer, 'longitude'),
                ]),
                'equipment' => $this->when($this->relationLoaded('equipment'), fn () => $this->equipment ? [
                    'brand' => $this->safeRelatedAttribute($this->equipment, 'brand'),
                    'model' => $this->safeRelatedAttribute($this->equipment, 'model'),
                    'serial_number' => $this->safeRelatedAttribute($this->equipment, 'serial_number'),
                    'tag' => $this->safeRelatedAttribute($this->equipment, 'tag'),
                ] : null),
                'equipments_list' => $this->when($this->relationLoaded('equipmentsList'), fn () => $this->equipmentsList->map(fn ($equipment) => [
                    'brand' => $this->safeRelatedAttribute($equipment, 'brand'),
                    'model' => $this->safeRelatedAttribute($equipment, 'model'),
                    'serial_number' => $this->safeRelatedAttribute($equipment, 'serial_number'),
                    'tag' => $this->safeRelatedAttribute($equipment, 'tag'),
                ])->values()),
                'items' => $this->when($this->relationLoaded('items'), fn () => $this->items->map(fn ($item) => [
                    'id' => $item->id,
                    'type' => $item->type,
                    'reference_id' => $item->reference_id,
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'discount' => $item->discount,
                    'total' => $item->total,
                ])->values()),
                'status_history' => $this->when($this->relationLoaded('statusHistory'), fn () => $this->statusHistory->map(fn ($history) => [
                    'status' => $this->safeRelatedAttribute($history, 'status'),
                    'notes' => $this->safeRelatedAttribute($history, 'notes'),
                    'created_at' => $history->created_at?->toIso8601String(),
                    'user' => $history->relationLoaded('user') && $history->user ? [
                        'name' => $history->user->name,
                    ] : null,
                ])->values()),
                'attachments' => $this->when($this->relationLoaded('attachments'), fn () => $this->attachments->map(fn ($attachment) => [
                    'id' => $attachment->id,
                    'file_name' => $attachment->file_name,
                    'file_path' => $attachment->file_path,
                    'file_type' => $attachment->file_type,
                    'created_at' => $attachment->created_at?->toIso8601String(),
                ])->values()),
            ];
        }

        $arr = [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'os_number' => $this->os_number,
            'number' => $this->number,
            'customer_id' => $this->customer_id,
            'equipment_id' => $this->equipment_id,
            'quote_id' => $this->quote_id,
            'service_call_id' => $this->service_call_id,
            'recurring_contract_id' => $this->recurring_contract_id,
            'seller_id' => $this->seller_id,
            'driver_id' => $this->driver_id,
            'branch_id' => $this->branch_id,
            'created_by' => $this->created_by,
            'assigned_to' => $this->assigned_to,
            'status' => $this->status,
            'priority' => $this->priority,
            'description' => $this->description,
            'internal_notes' => $this->internal_notes,
            'technical_report' => $this->technical_report,
            'scheduled_date' => $this->scheduled_date?->toIso8601String(),
            'received_at' => $this->received_at?->toIso8601String(),
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'discount' => $this->discount,
            'discount_percentage' => $this->discount_percentage,
            'discount_amount' => $this->discount_amount,
            'displacement_value' => $this->displacement_value,
            'total' => $this->total,
            'signature_path' => $this->signature_path,
            'signature_signer' => $this->signature_signer,
            'signature_at' => $this->signature_at?->toIso8601String(),
            'signature_ip' => $this->signature_ip,
            'agreed_payment_method' => $this->agreed_payment_method,
            'agreed_payment_notes' => $this->agreed_payment_notes,
            'delivery_forecast' => $this->delivery_forecast?->toDateString(),
            'tags' => $this->tags ?? [],
            'photo_checklist' => $this->photo_checklist,
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancellation_reason' => $this->cancellation_reason,
            // Campos de execução de campo
            'displacement_started_at' => $this->displacement_started_at?->toIso8601String(),
            'displacement_arrived_at' => $this->displacement_arrived_at?->toIso8601String(),
            'displacement_duration_minutes' => $this->displacement_duration_minutes,
            'service_started_at' => $this->service_started_at?->toIso8601String(),
            'wait_time_minutes' => $this->wait_time_minutes,
            'service_duration_minutes' => $this->service_duration_minutes,
            'total_duration_minutes' => $this->total_duration_minutes,
            'arrival_latitude' => $this->arrival_latitude,
            'arrival_longitude' => $this->arrival_longitude,
            'checkin_at' => $this->checkin_at?->toIso8601String(),
            'checkin_lat' => $this->checkin_lat,
            'checkin_lng' => $this->checkin_lng,
            'checkout_at' => $this->checkout_at?->toIso8601String(),
            'checkout_lat' => $this->checkout_lat,
            'checkout_lng' => $this->checkout_lng,
            'auto_km_calculated' => $this->auto_km_calculated,
            // Retorno
            'return_started_at' => $this->return_started_at?->toIso8601String(),
            'return_arrived_at' => $this->return_arrived_at?->toIso8601String(),
            'return_duration_minutes' => $this->return_duration_minutes,
            'return_destination' => $this->return_destination,
            // SLA
            'sla_policy_id' => $this->sla_policy_id,
            'sla_due_at' => $this->sla_due_at?->toIso8601String(),
            'sla_responded_at' => $this->sla_responded_at?->toIso8601String(),
            'checklist_id' => $this->checklist_id,
            // Despacho
            'dispatch_authorized_by' => $this->dispatch_authorized_by,
            'dispatch_authorized_at' => $this->dispatch_authorized_at?->toIso8601String(),
            // Detalhes adicionais
            'parent_id' => $this->parent_id,
            'is_master' => $this->is_master,
            'is_warranty' => $this->is_warranty,
            'service_type' => $this->service_type,
            'manual_justification' => $this->manual_justification,
            'origin_type' => $this->origin_type,
            'lead_source' => $this->lead_source,
            'warranty_until' => $this->warranty_until?->toIso8601String(),
            'is_under_warranty' => $this->is_under_warranty,
            'waze_link' => $this->waze_link,
            'google_maps_link' => $this->google_maps_link,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'address' => $this->address,
            'city' => $this->city,
            'state' => $this->state,
            'zip_code' => $this->zip_code,
            'contact_phone' => $this->contact_phone,
        ];

        if ($this->relationLoaded('customer')) {
            $arr['customer'] = $this->customer;
        }
        if ($this->relationLoaded('equipment')) {
            $arr['equipment'] = $this->equipment;
        }
        if ($this->relationLoaded('assignee')) {
            $arr['assignee'] = $this->assignee;
        }
        if ($this->relationLoaded('seller')) {
            $arr['seller'] = $this->seller;
        }
        if ($this->relationLoaded('technicians')) {
            $arr['technicians'] = $this->technicians;
        }
        if ($this->relationLoaded('equipmentsList')) {
            $arr['equipments_list'] = $this->equipmentsList;
        }
        if ($this->relationLoaded('items')) {
            $arr['items'] = $this->items;
            $arr['estimated_profit'] = $this->estimated_profit;
        }
        if ($this->relationLoaded('statusHistory')) {
            $arr['status_history'] = $this->statusHistory;
        }
        if ($this->relationLoaded('quote')) {
            $arr['quote'] = $this->quote;
        }
        if ($this->relationLoaded('serviceCall')) {
            $arr['service_call'] = $this->serviceCall;
        }
        if ($this->relationLoaded('attachments')) {
            $arr['attachments'] = $this->attachments;
        }
        if ($this->relationLoaded('parent')) {
            $arr['parent'] = $this->parent;
        }
        if ($this->relationLoaded('children')) {
            $arr['children'] = $this->children;
        }
        if ($this->relationLoaded('creator')) {
            $arr['creator'] = $this->creator;
        }
        if ($this->relationLoaded('driver')) {
            $arr['driver'] = $this->driver;
        }
        if ($this->relationLoaded('checklistResponses')) {
            $arr['checklist_responses'] = $this->checklistResponses;
        }
        if ($this->relationLoaded('displacementStops')) {
            $arr['displacement_stops'] = $this->displacementStops;
        }
        if ($this->relationLoaded('calibrations')) {
            $arr['calibrations'] = $this->calibrations;
        }
        if ($this->relationLoaded('branch')) {
            $arr['branch'] = $this->branch;
        }
        if ($this->relationLoaded('invoices')) {
            $arr['invoices'] = $this->invoices;
        }
        if ($this->relationLoaded('satisfactionSurvey')) {
            $arr['satisfaction_survey'] = $this->satisfactionSurvey;
        }
        if ($this->relationLoaded('chats')) {
            $arr['chats'] = $this->chats;
        }
        if ($this->relationLoaded('dispatchAuthorizer')) {
            $arr['dispatch_authorizer'] = $this->dispatchAuthorizer;
        }
        if ($this->relationLoaded('ratings')) {
            $arr['ratings'] = $this->ratings;
        }
        if ($this->relationLoaded('timeLogs')) {
            $arr['time_logs'] = $this->timeLogs;
        }
        if ($this->relationLoaded('deals')) {
            $arr['deals'] = $this->deals;
        }
        if ($this->relationLoaded('stockMovements')) {
            $arr['stock_movements'] = $this->stockMovements;
        }
        if ($this->relationLoaded('payments')) {
            $arr['payments'] = $this->payments;
        }
        if ($this->relationLoaded('maintenanceReports')) {
            $arr['maintenance_reports'] = MaintenanceReportResource::collection($this->maintenanceReports);
        }

        // Campos de Análise Crítica (ISO 17025)
        $arr['service_modality'] = $this->service_modality;
        $arr['requires_adjustment'] = $this->requires_adjustment;
        $arr['requires_maintenance'] = $this->requires_maintenance;
        $arr['client_wants_conformity_declaration'] = $this->client_wants_conformity_declaration;
        $arr['decision_rule_agreed'] = $this->decision_rule_agreed;
        $arr['subject_to_legal_metrology'] = $this->subject_to_legal_metrology;
        $arr['needs_ipem_interaction'] = $this->needs_ipem_interaction;
        $arr['site_conditions'] = $this->site_conditions;
        $arr['calibration_scope_notes'] = $this->calibration_scope_notes;
        $arr['will_emit_complementary_report'] = $this->will_emit_complementary_report;

        $arr['business_number'] = $this->business_number;
        $statusEnum = WorkOrderStatus::tryFrom($this->status);
        $arr['allowed_transitions'] = $statusEnum
            ? array_map(fn ($s) => $s->value, $statusEnum->allowedTransitions())
            : [];

        return $arr;
    }

    private function isExternalPortalRequest(Request $request): bool
    {
        return $request->user() instanceof ClientPortalUser
            || $request->is('api/v1/portal/*');
    }

    private function safeRelatedAttribute(?object $model, string $key): mixed
    {
        if (! $model instanceof Model) {
            return null;
        }

        if (! array_key_exists($key, $model->getAttributes())) {
            return null;
        }

        return $model->getAttribute($key);
    }
}
