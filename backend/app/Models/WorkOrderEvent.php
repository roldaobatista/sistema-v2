<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property array<int|string, mixed>|null $metadata
 * @property numeric-string|null $latitude
 * @property numeric-string|null $longitude
 */
class WorkOrderEvent extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'work_order_id',
        'event_type',
        'user_id',
        'latitude',
        'longitude',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
        ];
    }

    public const TYPE_DISPLACEMENT_STARTED = 'displacement_started';

    public const TYPE_DISPLACEMENT_PAUSED = 'displacement_paused';

    public const TYPE_DISPLACEMENT_RESUMED = 'displacement_resumed';

    public const TYPE_ARRIVED_AT_CLIENT = 'arrived_at_client';

    public const TYPE_SERVICE_STARTED = 'service_started';

    public const TYPE_SERVICE_PAUSED = 'service_paused';

    public const TYPE_SERVICE_RESUMED = 'service_resumed';

    public const TYPE_SERVICE_COMPLETED = 'service_completed';

    public const TYPE_RETURN_STARTED = 'return_started';

    public const TYPE_RETURN_PAUSED = 'return_paused';

    public const TYPE_RETURN_RESUMED = 'return_resumed';

    public const TYPE_RETURN_ARRIVED = 'return_arrived';

    public const TYPE_CLOSED_NO_RETURN = 'closed_no_return';

    public const TYPE_CHECKIN_REGISTERED = 'checkin_registered';

    public const TYPE_CHECKOUT_REGISTERED = 'checkout_registered';

    public const TYPE_STATUS_CHANGED = 'status_changed';

    public const TYPE_LABELS = [
        self::TYPE_DISPLACEMENT_STARTED => 'Deslocamento iniciado',
        self::TYPE_DISPLACEMENT_PAUSED => 'Deslocamento pausado',
        self::TYPE_DISPLACEMENT_RESUMED => 'Deslocamento retomado',
        self::TYPE_ARRIVED_AT_CLIENT => 'Chegou no cliente',
        self::TYPE_SERVICE_STARTED => 'Serviço iniciado',
        self::TYPE_SERVICE_PAUSED => 'Serviço pausado',
        self::TYPE_SERVICE_RESUMED => 'Serviço retomado',
        self::TYPE_SERVICE_COMPLETED => 'Serviço finalizado',
        self::TYPE_RETURN_STARTED => 'Retorno iniciado',
        self::TYPE_RETURN_PAUSED => 'Retorno pausado',
        self::TYPE_RETURN_RESUMED => 'Retorno retomado',
        self::TYPE_RETURN_ARRIVED => 'Chegou no destino',
        self::TYPE_CLOSED_NO_RETURN => 'OS encerrada (sem retorno)',
        self::TYPE_CHECKIN_REGISTERED => 'Check-in registrado',
        self::TYPE_CHECKOUT_REGISTERED => 'Check-out registrado',
        self::TYPE_STATUS_CHANGED => 'Status alterado',
    ];

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
