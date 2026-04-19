<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property numeric-string|null $base_lat
 * @property numeric-string|null $base_lng
 * @property int|null $max_distance_km
 * @property array<int|string, mixed>|null $enrichment_sources
 * @property Carbon|null $last_enrichment_at
 * @property Carbon|null $last_rejection_check_at
 * @property array<int|string, mixed>|null $notification_roles
 */
class InmetroBaseConfig extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'base_lat',
        'base_lng',
        'base_address',
        'base_city',
        'base_state',
        'max_distance_km',
        'enrichment_sources',
        'last_enrichment_at',
        // PSIE credentials (encrypted)
        'psie_username',
        'psie_password',
        // Rejection tracking
        'last_rejection_check_at',
        // Notification config
        'notification_roles',
        // WhatsApp message template
        'whatsapp_message_template',
        // Email template
        'email_subject_template',
        'email_body_template',
    ];

    /**
     * qa-07 (Re-auditoria Camada 1): credencial PSIE nao vaza em toArray/toJson.
     */
    protected $hidden = [
        'psie_password',
    ];

    protected function casts(): array
    {
        return [
            'base_lat' => 'decimal:7',
            'base_lng' => 'decimal:7',
            'max_distance_km' => 'integer',
            'enrichment_sources' => 'array',
            'last_enrichment_at' => 'datetime',
            'last_rejection_check_at' => 'datetime',
            'notification_roles' => 'array',
            'psie_password' => 'encrypted',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
