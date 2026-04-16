<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property bool|null $notify_assigned_to_me
 * @property bool|null $notify_created_by_me
 * @property bool|null $notify_watching
 * @property bool|null $notify_mentioned
 * @property array<int|string, mixed>|null $quiet_hours
 * @property array<int|string, mixed>|null $notify_types
 */
class AgendaNotificationPreference extends Model
{
    use BelongsToTenant;

    protected $table = 'central_notification_prefs';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'notify_assigned_to_me' => 'boolean',
            'notify_created_by_me' => 'boolean',
            'notify_watching' => 'boolean',
            'notify_mentioned' => 'boolean',
            'quiet_hours' => 'array',
            'notify_types' => 'array',
        ];

    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public static function forUser(int $userId, int $tenantId): self
    {
        return static::firstOrCreate(
            ['user_id' => $userId, 'tenant_id' => $tenantId],
            [
                'notify_assigned_to_me' => true,
                'notify_created_by_me' => true,
                'notify_watching' => true,
                'notify_mentioned' => true,
                'channel_in_app' => 'on',
                'channel_email' => 'off',
                'channel_push' => 'on',
            ]
        );
    }

    public function isChannelEnabled(string $channel): bool
    {
        $value = $this->getAttribute("channel_{$channel}");

        return $value === 'on' || $value === 'digest';
    }

    public function isInQuietHours(): bool
    {
        if (! $this->quiet_hours) {
            return false;
        }

        $now = now()->format('H:i');
        $start = $this->quiet_hours['start'] ?? null;
        $end = $this->quiet_hours['end'] ?? null;

        if (! $start || ! $end) {
            return false;
        }

        if ($start <= $end) {
            return $now >= $start && $now <= $end;
        }

        return $now >= $start || $now <= $end;
    }

    public function shouldNotifyForType(string $itemType): bool
    {
        $types = $this->notify_types;

        if (empty($types)) {
            // pwa_mode may not exist if migration 200002 hasn't run yet
            $pwaMode = $this->getAttribute('pwa_mode');
            $types = self::defaultTypesForMode($pwaMode);
        }

        if (empty($types)) {
            return true;
        }

        return in_array(strtoupper($itemType), array_map('strtoupper', $types), true);
    }

    public static function defaultTypesForMode(?string $mode): array
    {
        return match ($mode) {
            'tecnico' => ['OS', 'CHAMADO', 'TAREFA', 'LEMBRETE', 'CALIBRACAO'],
            'vendedor' => ['ORCAMENTO', 'TAREFA', 'LEMBRETE', 'CONTRATO'],
            'gestao' => [],
            default => [],
        };
    }
}
