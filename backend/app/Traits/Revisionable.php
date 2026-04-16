<?php

namespace App\Traits;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Revisionable Trait (5.45)
 * Automatically logs model changes to system_revisions table for temporal rollback.
 */
trait Revisionable
{
    public static function bootRevisionable(): void
    {
        static::updating(function ($model) {
            $original = $model->getOriginal();
            $changed = $model->getDirty();

            if (empty($changed)) {
                return;
            }

            $before = array_intersect_key($original, $changed);

            DB::table('system_revisions')->insert([
                'tenant_id' => $model->tenant_id ?? null,
                'model_type' => get_class($model),
                'model_id' => $model->getKey(),
                'before_payload' => json_encode($before),
                'after_payload' => json_encode($changed),
                'action' => 'update',
                'user_id' => Auth::id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        static::deleting(function ($model) {
            DB::table('system_revisions')->insert([
                'tenant_id' => $model->tenant_id ?? null,
                'model_type' => get_class($model),
                'model_id' => $model->getKey(),
                'before_payload' => json_encode($model->toArray()),
                'after_payload' => null,
                'action' => 'delete',
                'user_id' => Auth::id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    /**
     * Rollback to a specific revision.
     */
    public static function rollbackToRevision(int $revisionId): ?self
    {
        $revision = DB::table('system_revisions')->find($revisionId);
        if (! $revision || $revision->model_type !== static::class) {
            return null;
        }

        $model = static::find($revision->model_id);
        if (! $model) {
            return null;
        }

        $beforePayload = json_decode($revision->before_payload, true);
        if (empty($beforePayload)) {
            return null;
        }

        $model->update($beforePayload);

        return $model->fresh();
    }
}
