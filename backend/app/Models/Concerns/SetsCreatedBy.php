<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\Auth;

/**
 * Trait que auto-seta created_by do user autenticado se não fornecido.
 *
 * Aplicar em models que possuem coluna created_by NOT NULL.
 * Se nenhum user estiver autenticado, usa 0 como fallback (system user).
 */
trait SetsCreatedBy
{
    public static function bootSetsCreatedBy(): void
    {
        static::creating(function ($model) {
            if (empty($model->created_by)) {
                $model->created_by = Auth::id() ?? 0;
            }
        });
    }
}
