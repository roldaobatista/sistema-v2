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
            'tipo' => $this->tipo?->value ?? $this->tipo,
            'titulo' => $this->titulo,
            'descricao_curta' => $this->descricao_curta,
            'status' => $this->status?->value ?? $this->status,
            'prioridade' => $this->prioridade?->value ?? $this->prioridade,
            'origem' => $this->origem?->value ?? $this->origem,
            'visibilidade' => $this->visibilidade?->value ?? $this->visibilidade,
            'due_at' => $this->due_at?->toIso8601String(),
            'remind_at' => $this->remind_at?->toIso8601String(),
            'snooze_until' => $this->snooze_until?->toIso8601String(),
            'sla_due_at' => $this->sla_due_at?->toIso8601String(),
            'recurrence_next_at' => $this->recurrence_next_at?->toIso8601String(),
            'responsavel_user_id' => $this->responsavel_user_id,
            'criado_por_user_id' => $this->criado_por_user_id,
            'ref_tipo' => $this->ref_tipo,
            'ref_id' => $this->ref_id,
            'contexto' => $this->contexto,
            'tags' => $this->tags,
            'visibility_users' => $this->visibility_users,
            'visibility_departments' => $this->visibility_departments,
            'closed_at' => $this->closed_at?->toIso8601String(),
            'closed_by' => $this->closed_by,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];

        if ($this->relationLoaded('responsavel')) {
            $arr['responsavel'] = $this->responsavel;
        }
        if ($this->relationLoaded('criadoPor')) {
            $arr['criadoPor'] = $this->criadoPor;
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
