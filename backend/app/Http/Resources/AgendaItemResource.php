<?php

namespace App\Http\Resources;

use App\Models\AgendaItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AgendaItem
 */
class AgendaItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $arr = [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'type' => $this->type?->value ?? $this->type,
            'title' => $this->title,
            'short_description' => $this->short_description,
            'status' => $this->status?->value ?? $this->status,
            'priority' => $this->priority?->value ?? $this->priority,
            'origin' => $this->origin?->value ?? $this->origin,
            'visibility' => $this->visibility?->value ?? $this->visibility,
            'due_at' => $this->due_at?->toIso8601String(),
            'remind_at' => $this->remind_at?->toIso8601String(),
            'snooze_until' => $this->snooze_until?->toIso8601String(),
            'sla_due_at' => $this->sla_due_at?->toIso8601String(),
            'recurrence_next_at' => $this->recurrence_next_at?->toIso8601String(),
            'assignee_user_id' => $this->assignee_user_id,
            'created_by_user_id' => $this->created_by_user_id,
            'ref_type' => $this->ref_type,
            'ref_id' => $this->ref_id,
            'context' => $this->context,
            'tags' => $this->tags,
            'visibility_users' => $this->visibility_users,
            'visibility_departments' => $this->visibility_departments,
            'closed_at' => $this->closed_at?->toIso8601String(),
            'closed_by' => $this->closed_by,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            // Legacy PT aliases para compatibilidade com frontend/clientes existentes
            'tipo' => $this->type?->value ?? $this->type,
            'titulo' => $this->title,
            'descricao_curta' => $this->short_description,
            'prioridade' => $this->priority?->value ?? $this->priority,
            'visibilidade' => $this->visibility?->value ?? $this->visibility,
            'contexto' => $this->context,
            'ref_tipo' => $this->ref_type,
            'responsavel_user_id' => $this->assignee_user_id,
            'criado_por_user_id' => $this->created_by_user_id,
        ];

        if ($this->relationLoaded('assignee')) {
            $arr['assignee'] = $this->assignee;
        }
        if ($this->relationLoaded('creator')) {
            $arr['creator'] = $this->creator;
        }
        if ($this->relationLoaded('subtasks')) {
            $arr['subtasks'] = $this->subtasks;
        }
        if ($this->relationLoaded('attachments')) {
            $arr['attachments'] = $this->attachments;
        }
        if ($this->relationLoaded('watchers')) {
            $arr['watchers'] = $this->watchers;
        }
        if ($this->relationLoaded('comments')) {
            $arr['comments'] = $this->comments;
        }
        if ($this->relationLoaded('history')) {
            $arr['history'] = $this->history;
        }
        if ($this->relationLoaded('source')) {
            $arr['source'] = $this->source;
        }
        if ($this->relationLoaded('timeEntries')) {
            $arr['time_entries'] = $this->timeEntries;
        }
        if ($this->relationLoaded('dependsOn')) {
            $arr['depends_on'] = $this->dependsOn;
        }

        return $arr;
    }
}
