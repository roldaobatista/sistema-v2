<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property array<int|string, mixed>|null $anonymized_fields
 */
class LgpdAnonymizationLog extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'entity_type', 'entity_id', 'holder_document',
        'anonymized_fields', 'legal_basis', 'executed_by',
    ];

    protected function casts(): array
    {
        return [
            'anonymized_fields' => 'array',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function entity(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function executor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executed_by');
    }
}
