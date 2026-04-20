<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Auto-preenche `updated_by` e `deleted_by` (quando suportado pelo schema) com
 * o ID do usuário autenticado. Aplicar em models cujas tabelas possuem essas
 * colunas — complementa `SetsCreatedBy`.
 *
 * - `updated_by` é setado em todo `updating` se ainda não foi atribuído.
 * - `deleted_by` é setado antes do soft-delete (saveQuietly + delete não
 *   dispararia o observer de updating, então setamos via `deleting`).
 *
 * Se nenhum user estiver autenticado, NADA é alterado (mantém valor anterior).
 */
trait HasAuditUserFields
{
    public static function bootHasAuditUserFields(): void
    {
        static::updating(function (Model $model) {
            if (! Auth::check()) {
                return;
            }
            if (! $model->isDirty('updated_by')) {
                $model->setAttribute('updated_by', Auth::id());
            }
        });

        static::deleting(function (Model $model) {
            if (! Auth::check()) {
                return;
            }
            // Soft-delete: registra quem deletou.
            // Usa saveQuietly para não disparar Auditable.updated em loop.
            if (in_array('deleted_by', (array) $model->getFillable(), true)) {
                $model->setAttribute('deleted_by', Auth::id());
                $model->saveQuietly();
            }
        });
    }
}
