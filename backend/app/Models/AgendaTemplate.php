<?php

namespace App\Models;

use App\Enums\AgendaItemStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property array<int|string, mixed>|null $subtasks
 * @property array<int|string, mixed>|null $default_watchers
 * @property array<int|string, mixed>|null $tags
 * @property bool|null $ativo
 * @property int|null $due_days
 */
class AgendaTemplate extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'central_templates';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'subtasks' => 'array',
            'default_watchers' => 'array',
            'tags' => 'array',
            'ativo' => 'boolean',
            'due_days' => 'integer',
        ];

    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function gerarItem(int $responsavelId, array $overrides = []): AgendaItem
    {
        $user = auth()->user();
        $tenantId = $this->tenant_id;

        $item = AgendaItem::create(array_merge([
            'tenant_id' => $tenantId,
            'tipo' => strtolower($this->tipo),
            'origem' => 'manual',
            'titulo' => $overrides['titulo'] ?? $this->nome,
            'descricao_curta' => $overrides['descricao_curta'] ?? $this->descricao,
            'responsavel_user_id' => $responsavelId,
            'criado_por_user_id' => $user?->id ?? $responsavelId,
            'status' => AgendaItemStatus::ABERTO,
            'prioridade' => strtolower($this->prioridade),
            'visibilidade' => strtolower($this->visibilidade),
            'due_at' => $this->due_days ? now()->addDays($this->due_days) : null,
            'tags' => $this->tags,
        ], $overrides));

        if (! empty($this->subtasks)) {
            foreach ($this->subtasks as $i => $sub) {
                $title = is_array($sub) ? ($sub['titulo'] ?? $sub['title'] ?? '') : (string) $sub;
                if ($title) {
                    $item->subtasks()->create([
                        'tenant_id' => $tenantId,
                        'titulo' => $title,
                        'ordem' => $i,
                    ]);
                }
            }
        }

        $watcherIds = collect($this->default_watchers ?? [])
            ->push($user?->id)
            ->push($responsavelId)
            ->filter()
            ->unique()
            ->values();

        foreach ($watcherIds as $wid) {
            AgendaItemWatcher::firstOrCreate(
                array_merge(AgendaItemWatcher::itemForeignAttributes($item->id), ['user_id' => (int) $wid]),
                [
                    'role' => 'watcher',
                    'added_by_type' => 'template',
                    'notify_status_change' => true,
                    'notify_comment' => true,
                    'notify_due_date' => true,
                    'notify_assignment' => true,
                ]
            );
        }

        return $item;
    }
}
