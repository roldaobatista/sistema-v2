<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string|null $type
 * @property int|null $customer_id
 * @property int|null $deal_id
 * @property int|null $user_id
 * @property int|null $contact_id
 * @property string|null $title
 * @property string|null $description
 * @property Carbon|null $scheduled_at
 * @property Carbon|null $completed_at
 * @property int|null $duration_minutes
 * @property string|null $outcome
 * @property bool $is_automated
 * @property string|null $channel
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Customer|null $customer
 * @property-read CrmDeal|null $deal
 * @property-read User|null $user
 * @property-read CustomerContact|null $contact
 */
class CrmActivity extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id', 'type', 'customer_id', 'deal_id', 'user_id',
        'contact_id', 'title', 'description', 'scheduled_at', 'completed_at',
        'duration_minutes', 'outcome', 'is_automated', 'channel',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'completed_at' => 'datetime',
            'is_automated' => 'boolean',
            'metadata' => 'array',
        ];
    }

    // ─── Type & Channel constants ──────────────────────
    public const TYPE_TASK = 'tarefa';

    public const TYPE_SYSTEM = 'system';

    public const TYPE_CALL = 'ligacao';

    public const TYPE_EMAIL = 'email';

    public const TYPE_MEETING = 'reuniao';

    public const TYPE_VISIT = 'visita';

    public const TYPE_WHATSAPP = 'whatsapp';

    public const TYPE_NOTE = 'nota';

    public const CHANNEL_PHONE = 'phone';

    public const CHANNEL_WHATSAPP = 'whatsapp';

    public const CHANNEL_EMAIL = 'email';

    public const CHANNEL_IN_PERSON = 'in_person';

    const TYPES = [
        self::TYPE_CALL => ['label' => 'Ligação', 'icon' => 'phone'],
        self::TYPE_EMAIL => ['label' => 'E-mail', 'icon' => 'mail'],
        self::TYPE_MEETING => ['label' => 'Reunião', 'icon' => 'users'],
        self::TYPE_VISIT => ['label' => 'Visita', 'icon' => 'map-pin'],
        self::TYPE_WHATSAPP => ['label' => 'WhatsApp', 'icon' => 'message-circle'],
        self::TYPE_NOTE => ['label' => 'Nota', 'icon' => 'file-text'],
        self::TYPE_TASK => ['label' => 'Tarefa', 'icon' => 'check-square'],
        self::TYPE_SYSTEM => ['label' => 'Sistema', 'icon' => 'cpu'],
    ];

    const OUTCOMES = [
        'conectou' => 'Conectou',
        'nao_atendeu' => 'Não Atendeu',
        'reagendar' => 'Reagendar',
        'sucesso' => 'Sucesso',
        'sem_interesse' => 'Sem Interesse',
    ];

    const CHANNELS = [
        self::CHANNEL_WHATSAPP => 'WhatsApp',
        self::CHANNEL_EMAIL => 'E-mail',
        self::CHANNEL_PHONE => 'Phone',
        self::CHANNEL_IN_PERSON => 'In Person',
    ];

    // ─── Scopes ─────────────────────────────────────────

    public function scopePending($q)
    {
        return $q->whereNull('completed_at')->whereNotNull('scheduled_at');
    }

    public function scopeCompleted($q)
    {
        return $q->whereNotNull('completed_at');
    }

    public function scopeByType($q, string $type)
    {
        return $q->where('type', $type);
    }

    public function scopeUpcoming($q)
    {
        return $q->whereNull('completed_at')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '>=', now())
            ->orderBy('scheduled_at');
    }

    // ─── Factory Methods ────────────────────────────────

    public static function logSystemEvent(
        int $tenantId,
        int $customerId,
        string $title,
        ?int $dealId = null,
        ?int $userId = null,
        ?array $metadata = null
    ): static {
        return static::create([
            'tenant_id' => $tenantId,
            'type' => self::TYPE_SYSTEM,
            'customer_id' => $customerId,
            'deal_id' => $dealId,
            'user_id' => $userId ?? (Auth::check() ? Auth::id() : null),
            'title' => $title,
            'is_automated' => true,
            'completed_at' => now(),
            'metadata' => $metadata,
        ]);
    }

    // ─── Relationships ──────────────────────────────────

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(CrmDeal::class, 'deal_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(CustomerContact::class, 'contact_id');
    }
}
