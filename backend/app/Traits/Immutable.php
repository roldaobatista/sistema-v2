<?php

namespace App\Traits;

use App\Models\TimeClockAuditLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Trait Immutable
 *
 * Prevents modification of original clock data after creation,
 * as required by Brazil's Portaria 671/2021.
 *
 * Protected fields (clock data) cannot be changed after initial save.
 * Allowed fields (administrative/hash) can still be updated.
 */
trait Immutable
{
    /**
     * Fields that CANNOT be modified after creation.
     */
    public function getImmutableFields(): array
    {
        return [
            'clock_in',
            'clock_out',
            'latitude_in',
            'longitude_in',
            'latitude_out',
            'longitude_out',
            'clock_method',
            'selfie_path',
        ];
    }

    /**
     * Fields that CAN be modified after creation.
     */
    public function getMutableFields(): array
    {
        return [
            'approval_status',
            'approved_by',
            'rejection_reason',
            'break_start',
            'break_end',
            'break_latitude',
            'break_longitude',
            'record_hash',
            'previous_hash',
            'hash_payload',
            'nsr',
            'notes',
        ];
    }

    /**
     * Boot the trait — register saving event to block immutable field changes.
     */
    public static function bootImmutable(): void
    {
        static::updating(function (Model $model) {
            // Only enforce immutability on existing records (not first save)
            if (! $model->exists) {
                return;
            }

            $immutableFields = $model->getImmutableFields();
            $dirty = $model->getDirty();

            foreach ($immutableFields as $field) {
                if (array_key_exists($field, $dirty)) {
                    $original = $model->getOriginal($field);
                    $new = $dirty[$field];

                    // Allow setting a field for the first time (null -> value)
                    // This is needed for clock_out which is null on clock-in
                    if ($original === null || $original === '' || $original === '0') {
                        continue;
                    }

                    // Log tampering attempt before throwing
                    try {
                        TimeClockAuditLog::log('tampering_attempt', $model->id, null, [
                            'field' => $field,
                            'original_value' => $model->getOriginal($field),
                            'attempted_value' => $model->getAttribute($field),
                        ]);
                    } catch (\Throwable $logError) {
                        \Log::warning('Failed to log tampering attempt', ['error' => $logError->getMessage()]);
                    }

                    // Block modification of already-set immutable fields
                    throw new \DomainException(
                        "Campo '{$field}' é imutável e não pode ser alterado após o registro. ".
                        'Conforme Portaria 671/2021, registros de ponto não podem ser modificados.'
                    );
                }
            }
        });
    }
}
