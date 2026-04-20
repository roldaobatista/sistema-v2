<?php

namespace App\Models;

use App\Enums\AgendaItemPriority;
use App\Enums\AgendaItemStatus;
use App\Enums\QuoteStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use App\Support\Decimal;
use App\Traits\SyncsWithAgenda;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string|null $quote_number
 * @property int $revision
 * @property int|null $customer_id
 * @property int|null $seller_id
 * @property int|null $created_by
 * @property int|null $template_id
 * @property int|null $opportunity_id
 * @property int|null $internal_approved_by
 * @property int|null $level2_approved_by
 * @property int $followup_count
 * @property int $client_view_count
 * @property QuoteStatus $status
 * @property string|null $source
 * @property string|null $currency
 * @property string|null $observations
 * @property string|null $internal_notes
 * @property string|null $payment_terms
 * @property string|null $payment_terms_detail
 * @property string|null $rejection_reason
 * @property string|null $magic_token
 * @property string|null $client_ip_approval
 * @property string|null $approval_channel
 * @property string|null $approval_notes
 * @property string|null $approved_by_name
 * @property bool $is_template
 * @property bool $is_installation_testing
 * @property float|null $discount_percentage
 * @property float|null $discount_amount
 * @property float|null $displacement_value
 * @property float|null $subtotal
 * @property float|null $total
 * @property int|null $validity_days
 * @property array|null $custom_fields
 * @property Carbon|null $valid_until
 * @property Carbon|null $internal_approved_at
 * @property Carbon|null $level2_approved_at
 * @property Carbon|null $sent_at
 * @property Carbon|null $approved_at
 * @property Carbon|null $rejected_at
 * @property Carbon|null $last_followup_at
 * @property Carbon|null $client_viewed_at
 * @property Carbon|null $term_accepted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read string|null $approval_token
 * @property-read string|null $approval_url
 * @property-read string|null $pdf_url
 * @property-read string|null $public_access_token
 * @property-read bool $pending_internal_approval
 * @property-read Customer|null $customer
 * @property-read User|null $seller
 * @property-read User|null $internalApprover
 * @property-read User|null $level2Approver
 * @property-read Quote|null $template
 * @property-read Collection<int, QuoteEquipment> $equipments
 * @property-read Collection<int, WorkOrder> $workOrders
 * @property-read Collection<int, ServiceCall> $serviceCalls
 * @property-read Collection<int, QuoteTag> $tags
 * @property-read Collection<int, Email> $emails
 * @property-read Collection<int, AccountReceivable> $accountReceivables
 */
class Quote extends Model
{
    use Auditable, BelongsToTenant, \Illuminate\Database\Eloquent\Factories\HasFactory, SoftDeletes, SyncsWithAgenda;

    /**
     * @deprecated Use QuoteStatus Enum instead. Will be removed in a future version.
     */
    public const STATUS_DRAFT = 'draft';

    /** @deprecated Use QuoteStatus::PENDING_INTERNAL_APPROVAL */
    public const STATUS_PENDING_INTERNAL = 'pending_internal_approval';

    /** @deprecated Use QuoteStatus::INTERNALLY_APPROVED */
    public const STATUS_INTERNALLY_APPROVED = 'internally_approved';

    /** @deprecated Use QuoteStatus::SENT */
    public const STATUS_SENT = 'sent';

    /** @deprecated Use QuoteStatus::APPROVED */
    public const STATUS_APPROVED = 'approved';

    /** @deprecated Use QuoteStatus::REJECTED */
    public const STATUS_REJECTED = 'rejected';

    /** @deprecated Use QuoteStatus::EXPIRED */
    public const STATUS_EXPIRED = 'expired';

    /** @deprecated Use QuoteStatus::IN_EXECUTION */
    public const STATUS_IN_EXECUTION = 'in_execution';

    /** @deprecated Use QuoteStatus::INSTALLATION_TESTING */
    public const STATUS_INSTALLATION_TESTING = 'installation_testing';

    /** @deprecated Use QuoteStatus::RENEGOTIATION */
    public const STATUS_RENEGOTIATION = 'renegotiation';

    /** @deprecated Use QuoteStatus::INVOICED */
    public const STATUS_INVOICED = 'invoiced';

    /** Map used by PDF template (quote.blade.php) */
    public const STATUSES = [
        self::STATUS_DRAFT => ['label' => 'Rascunho', 'color' => 'gray'],
        self::STATUS_PENDING_INTERNAL => ['label' => 'Aguard. Aprovação Interna', 'color' => 'amber'],
        self::STATUS_INTERNALLY_APPROVED => ['label' => 'Aprovado Internamente', 'color' => 'teal'],
        self::STATUS_SENT => ['label' => 'Enviado', 'color' => 'blue'],
        self::STATUS_APPROVED => ['label' => 'Aprovado', 'color' => 'green'],
        self::STATUS_REJECTED => ['label' => 'Rejeitado', 'color' => 'red'],
        self::STATUS_EXPIRED => ['label' => 'Expirado', 'color' => 'amber'],
        self::STATUS_IN_EXECUTION => ['label' => 'Em Execução', 'color' => 'indigo'],
        self::STATUS_INSTALLATION_TESTING => ['label' => 'Instalação p/ Teste', 'color' => 'orange'],
        self::STATUS_RENEGOTIATION => ['label' => 'Em Renegociação', 'color' => 'rose'],
        self::STATUS_INVOICED => ['label' => 'Faturado', 'color' => 'indigo'],
    ];

    // GAP-03: Commercial source (affects seller commission %)
    public const SOURCES = [
        'prospeccao' => 'Prospecção',
        'retorno' => 'Retorno',
        'contato_direto' => 'Contato Direto',
        'indicacao' => 'Indicação',
    ];

    public const ACTIVITY_TYPE_APPROVED = 'quote_approved';

    protected $appends = [];

    protected $fillable = [
        'tenant_id', 'quote_number', 'revision', 'customer_id', 'seller_id', 'created_by', 'status',
        'source', 'valid_until', 'discount_percentage', 'discount_amount',
        'displacement_value', 'validity_days',
        'subtotal', 'total', 'currency', 'observations', 'internal_notes',
        'payment_terms', 'payment_terms_detail', 'template_id', 'is_template', 'general_conditions',
        'opportunity_id', 'custom_fields',
        'internal_approved_by', 'internal_approved_at',
        'level2_approved_by', 'level2_approved_at',
        'sent_at', 'approved_at', 'rejected_at', 'rejection_reason',
        'last_followup_at', 'followup_count',
        'client_viewed_at', 'client_view_count',
        'magic_token', 'client_ip_approval', 'term_accepted_at',
        'is_installation_testing',
        'approval_channel', 'approval_notes', 'approved_by_name',
    ];

    protected function casts(): array
    {
        return [
            'status' => QuoteStatus::class,
            'payment_terms' => 'string',
            'valid_until' => 'date',
            'revision' => 'integer',
            'discount_percentage' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'displacement_value' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'total' => 'decimal:2',
            'is_template' => 'boolean',
            'custom_fields' => 'array',
            'internal_approved_at' => 'datetime',
            'level2_approved_at' => 'datetime',
            'sent_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'last_followup_at' => 'datetime',
            'followup_count' => 'integer',
            'client_viewed_at' => 'datetime',
            'client_view_count' => 'integer',
            'term_accepted_at' => 'datetime',
            'is_installation_testing' => 'boolean',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuoteItem::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function internalApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'internal_approved_by');
    }

    public function canSendToClient(): bool
    {
        return $this->status === QuoteStatus::INTERNALLY_APPROVED;
    }

    public function requiresInternalApproval(): bool
    {
        return $this->status === QuoteStatus::PENDING_INTERNAL_APPROVAL;
    }

    public function equipments(): HasMany
    {
        return $this->hasMany(QuoteEquipment::class)->orderBy('sort_order');
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }

    public function serviceCalls(): HasMany
    {
        return $this->hasMany(ServiceCall::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(QuoteTemplate::class, 'template_id');
    }

    public function tags(): BelongsToMany
    {
        $relation = $this->belongsToMany(QuoteTag::class, 'quote_quote_tag')
            ->withPivot('tenant_id');

        return $this->tenant_id ? $relation->withPivotValue('tenant_id', $this->tenant_id) : $relation;
    }

    public function emails(): HasMany
    {
        return $this->hasMany(QuoteEmail::class);
    }

    public function accountReceivables(): HasMany
    {
        return $this->hasMany(AccountReceivable::class);
    }

    public function level2Approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'level2_approved_by');
    }

    // ── Margin & Installment helpers ──

    public function totalCost(): string
    {
        $this->loadMissing('equipments.items');
        $cost = '0.00';
        foreach ($this->equipments as $eq) {
            foreach ($eq->items as $item) {
                $itemCost = bcmul(Decimal::string($item->cost_price), Decimal::string($item->quantity), 2);
                $cost = bcadd($cost, $itemCost, 2);
            }
        }

        return $cost;
    }

    public function profitMargin(): string
    {
        $total = Decimal::string($this->total);
        if (bccomp($total, '0', 2) <= 0) {
            return '0.0';
        }
        $cost = $this->totalCost();
        $profit = bcsub($total, $cost, 2);
        // margin = (profit / total) * 100, rounded to 1 decimal
        $margin = bcmul(bcdiv($profit, $total, 4), '100', 1);

        return $margin;
    }

    public function installmentSimulation(): array
    {
        $total = Decimal::string($this->total);
        $installments = [2, 3, 6, 10, 12];
        $result = [];
        foreach ($installments as $n) {
            $result[] = [
                'installments' => $n,
                'value' => bcdiv($total, (string) $n, 2),
            ];
        }

        return $result;
    }

    public function requiresLevel2Approval(): bool
    {
        $threshold = QuoteApprovalThreshold::where('tenant_id', $this->tenant_id)
            ->where('is_active', true)
            ->where('required_level', 2)
            ->where('min_value', '<=', $this->total)
            ->where(function ($q) {
                $q->whereNull('max_value')
                    ->orWhere('max_value', '>=', $this->total);
            })
            ->first();

        return $threshold !== null;
    }

    public function recalculateTotal(): void
    {
        $this->load('equipments.items');

        $subtotal = '0.00';
        foreach ($this->equipments as $eq) {
            foreach ($eq->items as $item) {
                $subtotal = bcadd($subtotal, Decimal::string($item->subtotal), 2);
            }
        }
        $this->subtotal = $subtotal;

        if ((float) $this->discount_percentage > 0) {
            $discountAmount = bcmul($subtotal, bcdiv(Decimal::string($this->discount_percentage), '100', 6), 2);
            $this->discount_amount = $discountAmount;
        } else {
            $discountAmount = (string) ($this->discount_amount ?? '0.00');
        }

        $displacement = (string) ($this->displacement_value ?? '0.00');
        $total = bcsub($subtotal, $discountAmount, 2);
        $total = bcadd($total, $displacement, 2);
        $this->total = bccomp($total, '0', 2) < 0 ? '0.00' : $total;
        $this->saveQuietly();
    }

    public function isExpired(): bool
    {
        return $this->valid_until
            && $this->valid_until->copy()->endOfDay()->isPast()
            && in_array($this->status->value ?? $this->status, self::expirableStatuses(), true);
    }

    public static function expirableStatuses(): array
    {
        return [
            QuoteStatus::SENT->value,
            QuoteStatus::PENDING_INTERNAL_APPROVAL->value,
            QuoteStatus::INTERNALLY_APPROVED->value,
        ];
    }

    public static function nextNumber(int $tenantId): string
    {
        $cacheKey = "seq_quote_{$tenantId}";
        $lockKey = "lock_{$cacheKey}";

        return Cache::lock($lockKey, 5)->block(5, function () use ($tenantId, $cacheKey) {
            $configuredStart = (int) (SystemSetting::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('key', 'quote_sequence_start')
                ->value('value') ?? 1);
            $configuredStart = max(1, $configuredStart);

            $historicalMax = static::withTrashed()
                ->where('tenant_id', $tenantId)
                ->pluck('quote_number')
                ->map(fn (?string $number): int => self::extractNumericSequence($number))
                ->max() ?? 0;

            $floor = max($configuredStart - 1, $historicalMax);
            $current = (int) Cache::get($cacheKey, 0);

            if (! Cache::has($cacheKey) || $current < $floor) {
                Cache::forever($cacheKey, $floor);
            }

            $next = Cache::increment($cacheKey);

            return 'ORC-'.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
        });
    }

    private static function extractNumericSequence(?string $number): int
    {
        if (! $number) {
            return 0;
        }

        if (! preg_match('/\d+/', $number, $matches)) {
            return 0;
        }

        return (int) ($matches[0] ?? 0);
    }

    // ── Link público de aprovação ──

    public function getApprovalTokenAttribute(): string
    {
        return hash_hmac('sha256', "quote-approve-{$this->id}", config('app.key'));
    }

    public function getApprovalUrlAttribute(): string
    {
        if (! $this->magic_token) {
            return '';
        }

        $frontendUrl = rtrim($this->resolvePublicFrontendUrl(), '/');

        return "{$frontendUrl}/quotes/proposal/{$this->magic_token}";
    }

    public function getPdfUrlAttribute(): string
    {
        $baseUrl = rtrim($this->resolvePublicAppUrl(), '/');

        return "{$baseUrl}/api/v1/quotes/{$this->id}/public-pdf?token={$this->public_access_token}";
    }

    public function getPublicAccessTokenAttribute(): string
    {
        return $this->magic_token ?: $this->approval_token;
    }

    private function resolvePublicFrontendUrl(): string
    {
        $requestOrigin = $this->resolveRequestOrigin();
        if ($requestOrigin !== null) {
            return $requestOrigin;
        }

        $publicFrontendUrl = $this->normalizePublicUrl(config('app.public_frontend_url'));
        if ($publicFrontendUrl !== null) {
            return $publicFrontendUrl;
        }

        $frontendUrl = trim((string) config('app.frontend_url', config('app.url')));
        $appUrl = $this->resolvePublicAppUrl();

        $frontendHost = parse_url($frontendUrl, PHP_URL_HOST);
        if (is_string($frontendHost) && ! in_array($frontendHost, ['localhost', '127.0.0.1'], true)) {
            return $frontendUrl;
        }

        if ($appUrl !== '') {
            return $appUrl;
        }

        return $frontendUrl !== '' ? $frontendUrl : 'http://localhost:3000';
    }

    private function resolvePublicAppUrl(): string
    {
        $requestOrigin = $this->resolveRequestOrigin();
        if ($requestOrigin !== null) {
            return $requestOrigin;
        }

        $publicAppUrl = $this->normalizePublicUrl(config('app.public_app_url'));
        if ($publicAppUrl !== null) {
            return $publicAppUrl;
        }

        $appUrl = trim((string) config('app.url'));
        $appHost = parse_url($appUrl, PHP_URL_HOST);

        if (is_string($appHost) && ! in_array($appHost, ['localhost', '127.0.0.1'], true)) {
            return $appUrl;
        }

        return $appUrl !== '' ? $appUrl : 'http://localhost';
    }

    private function resolveRequestOrigin(): ?string
    {
        $request = request();
        $host = $request->getHost();
        if ($host === '' || in_array($host, ['localhost', '127.0.0.1'], true)) {
            return null;
        }

        return $request->getSchemeAndHttpHost();
    }

    private function normalizePublicUrl(mixed $url): ?string
    {
        $normalizedUrl = trim((string) $url);
        if ($normalizedUrl === '') {
            return null;
        }

        $host = parse_url($normalizedUrl, PHP_URL_HOST);
        if (! is_string($host) || in_array($host, ['localhost', '127.0.0.1'], true)) {
            return null;
        }

        return $normalizedUrl;
    }

    public static function verifyApprovalToken(int $quoteId, string $token): bool
    {
        $expected = hash_hmac('sha256', "quote-approve-{$quoteId}", config('app.key'));

        return hash_equals($expected, $token);
    }

    public function matchesPublicAccessToken(string $token): bool
    {
        $normalizedToken = trim($token);
        if ($normalizedToken === '') {
            return false;
        }

        if (! blank($this->magic_token) && hash_equals((string) $this->magic_token, $normalizedToken)) {
            return true;
        }

        return self::verifyApprovalToken($this->id, $normalizedToken);
    }

    public function centralSyncData(): array
    {
        $statusMap = [
            QuoteStatus::DRAFT->value => AgendaItemStatus::ABERTO,
            QuoteStatus::PENDING_INTERNAL_APPROVAL->value => AgendaItemStatus::EM_ANDAMENTO,
            QuoteStatus::INTERNALLY_APPROVED->value => AgendaItemStatus::EM_ANDAMENTO,
            QuoteStatus::SENT->value => AgendaItemStatus::EM_ANDAMENTO,
            QuoteStatus::APPROVED->value => AgendaItemStatus::CONCLUIDO,
            QuoteStatus::REJECTED->value => AgendaItemStatus::CANCELADO,
            QuoteStatus::EXPIRED->value => AgendaItemStatus::CANCELADO,
            QuoteStatus::IN_EXECUTION->value => AgendaItemStatus::EM_ANDAMENTO,
            QuoteStatus::INSTALLATION_TESTING->value => AgendaItemStatus::EM_ANDAMENTO,
            QuoteStatus::RENEGOTIATION->value => AgendaItemStatus::EM_ANDAMENTO,
            QuoteStatus::INVOICED->value => AgendaItemStatus::CONCLUIDO,
        ];

        $rawStatus = $this->status instanceof QuoteStatus ? $this->status->value : $this->status;

        return [
            'title' => "Orçamento #{$this->quote_number}",
            'status' => $statusMap[$rawStatus] ?? AgendaItemStatus::ABERTO,
            'priority' => $rawStatus === QuoteStatus::SENT->value ? AgendaItemPriority::ALTA : AgendaItemPriority::MEDIA,
        ];
    }
}
