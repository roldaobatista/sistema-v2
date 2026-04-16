<?php

namespace App\Traits;

use App\Models\AgendaItem;
use Illuminate\Support\Facades\Log;

/**
 * Trait para modelos que devem sincronizar status com AgendaItem.
 *
 * Quando um model de origem (OS, Chamado, etc.) é atualizado,
 * o AgendaItem correspondente é atualizado automaticamente.
 *
 * Usage: use SyncsWithAgenda; em WorkOrder, ServiceCall, Quote, etc.
 */
trait SyncsWithAgenda
{
    protected static function bootSyncsWithAgenda(): void
    {
        static::updated(function ($model) {
            // Só sincroniza se o model tem tenant_id
            if (! $model->tenant_id) {
                return;
            }

            try {
                $overrides = $model->centralSyncData();
                if (! empty($overrides)) {
                    AgendaItem::syncFromSource($model, $overrides);
                }
            } catch (\Throwable $e) {
                // Agenda sync is a side-effect — never block the primary operation
                Log::warning('SyncsWithAgenda failed', [
                    'model' => get_class($model),
                    'id' => $model->getKey(),
                    'error' => $e->getMessage(),
                ]);
            }
        });
    }

    /**
     * Retorna os dados que devem ser sincronizados no AgendaItem.
     * Override este método nos models que usam a trait.
     *
     * @return array Ex: ['status' => AgendaItemStatus::COMPLETED, 'titulo' => 'OS #123 - Concluída']
     */
    public function centralSyncData(): array
    {
        return [];
    }
}
