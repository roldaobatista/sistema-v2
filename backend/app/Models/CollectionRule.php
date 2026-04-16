<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property array<int|string, mixed>|null $steps
 * @property bool|null $is_active
 */
class CollectionRule extends Model
{
    use Auditable, BelongsToTenant;

    protected $table = 'collection_rules';

    protected $fillable = [
        'tenant_id', 'name', 'steps', 'is_active',
    ];

    /** Canais implementados na régua de cobrança (CollectionAutomationService). */
    public const CHANNELS = ['email', 'whatsapp', 'sms'];

    public const TEMPLATE_TYPES = [
        'reminder' => 'Lembrete',
        'warning' => 'Aviso',
        'final_notice' => 'Notificação Final',
        'legal' => 'Jurídico',
    ];

    protected function casts(): array
    {
        return [
            'steps' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
