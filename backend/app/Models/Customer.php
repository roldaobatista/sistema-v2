<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasEncryptedSearchableField;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $type
 * @property string $name
 * @property string|null $trade_name
 * @property string|null $document
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $phone2
 * @property string|null $notes
 * @property bool $is_active
 * @property string|null $address_zip
 * @property string|null $address_street
 * @property string|null $address_number
 * @property string|null $address_complement
 * @property string|null $address_neighborhood
 * @property string|null $address_city
 * @property string|null $address_state
 * @property float|null $latitude
 * @property float|null $longitude
 * @property string|null $google_maps_link
 * @property string|null $state_registration
 * @property string|null $municipal_registration
 * @property string|null $cnae_code
 * @property string|null $cnae_description
 * @property string|null $legal_nature
 * @property float|null $capital
 * @property bool $simples_nacional
 * @property bool $mei
 * @property string|null $company_status
 * @property Carbon|null $opened_at
 * @property bool $is_rural_producer
 * @property array|null $partners
 * @property array|null $secondary_activities
 * @property array|null $enrichment_data
 * @property Carbon|null $enriched_at
 * @property string|null $source
 * @property string|null $segment
 * @property string|null $company_size
 * @property float|null $annual_revenue_estimate
 * @property string|null $contract_type
 * @property Carbon|null $contract_start
 * @property Carbon|null $contract_end
 * @property float|null $health_score
 * @property Carbon|null $last_contact_at
 * @property Carbon|null $next_follow_up_at
 * @property int|null $assigned_seller_id
 * @property array|null $tags
 * @property string|null $rating
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read array|null $health_score_breakdown
 * @property-read Carbon|null $nearest_calibration_at
 * @property-read int|null $documents_count
 * @property-read User|null $assignedSeller
 * @property-read Collection<int, CustomerContact> $contacts
 * @property-read Collection<int, CustomerAddress> $addresses
 * @property-read Collection<int, Equipment> $equipments
 * @property-read Collection<int, CrmDeal> $deals
 * @property-read Collection<int, CrmActivity> $activities
 * @property-read Collection<int, WorkOrder> $workOrders
 * @property-read Collection<int, RecurringContract> $recurringContracts
 * @property-read Collection<int, Quote> $quotes
 * @property-read Collection<int, ServiceCall> $serviceCalls
 * @property-read Collection<int, AccountReceivable> $accountsReceivable
 */
class Customer extends Model
{
    use Auditable, BelongsToTenant, HasEncryptedSearchableField, HasFactory, Notifiable, SoftDeletes;

    /**
     * Campos encrypted que precisam de coluna *_hash para busca determinística.
     *
     * @var array<string, string>
     */
    protected array $encryptedSearchableFields = [
        'document' => 'document_hash',
    ];

    protected $fillable = [
        'tenant_id', 'type', 'name', 'trade_name', 'document', 'document_hash', 'asaas_id', 'email',
        'phone', 'phone2', 'notes', 'is_active',
        'address_zip', 'address_street', 'address_number',
        'address_complement', 'address_neighborhood',
        'address_city', 'address_state',
        'latitude', 'longitude', 'google_maps_link',
        // Enrichment fields
        'state_registration', 'municipal_registration',
        'cnae_code', 'cnae_description', 'legal_nature',
        'capital', 'simples_nacional', 'mei',
        'company_status', 'opened_at', 'is_rural_producer',
        'partners', 'secondary_activities',
        'enrichment_data', 'enriched_at',
        // CRM fields
        'source', 'segment', 'company_size', 'annual_revenue_estimate',
        'contract_type', 'contract_start', 'contract_end', 'health_score',
        'last_contact_at', 'next_follow_up_at', 'assigned_seller_id',
        'tags', 'rating',
    ];

    /**
     * SEC-021 (Audit Camada 1, Wave 1D): `document_hash` é hash determinístico
     * (HMAC-SHA256 com APP_KEY) usado para busca em coluna encrypted. Expor em
     * payloads JSON viabiliza ataque de dicionário offline contra CPF/CNPJ —
     * domínio enumerável (~10^11). Ocultar de toArray()/toJson() preserva o
     * benefício de busca interna sem vazamento da prova de existência do PII.
     *
     * @var list<string>
     */
    protected $hidden = [
        'document_hash',
    ];

    private static array $brazilianStates = [
        'AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS',
        'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC',
        'SP', 'SE', 'TO',
    ];

    protected static function booted(): void
    {
        static::saving(function (Customer $customer) {
            if (! empty($customer->address_street)
                && empty($customer->address_city)
                && empty($customer->address_state)
            ) {
                $parsed = static::parseCityStateFromAddress($customer->address_street);
                if ($parsed) {
                    $customer->address_city = $parsed['city'];
                    $customer->address_state = $parsed['state'];
                }
            }
        });
    }

    public static function parseCityStateFromAddress(string $address): ?array
    {
        // "..., Cidade - UF, Brasil" ou "..., Cidade – UF"
        if (preg_match('/,\s*(.+?)\s*[\-\x{2013}\x{2014}]\s*([A-Z]{2})(?:\s*,\s*Brasil)?$/iu', $address, $m)) {
            $uf = strtoupper($m[2]);
            if (in_array($uf, static::$brazilianStates, true)) {
                return ['city' => trim($m[1]), 'state' => $uf];
            }
        }

        // "..., Cidade/UF"
        if (preg_match('/,\s*(.+?)\/([A-Z]{2})(?:\s*,\s*Brasil)?$/i', $address, $m)) {
            $uf = strtoupper($m[2]);
            if (in_array($uf, static::$brazilianStates, true)) {
                return ['city' => trim($m[1]), 'state' => $uf];
            }
        }

        return null;
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_rural_producer' => 'boolean',
            'simples_nacional' => 'boolean',
            'mei' => 'boolean',
            'capital' => 'decimal:2',
            'annual_revenue_estimate' => 'decimal:2',
            'opened_at' => 'date',
            'contract_start' => 'date',
            'contract_end' => 'date',
            'last_contact_at' => 'datetime',
            'next_follow_up_at' => 'datetime',
            'enriched_at' => 'datetime',
            'tags' => 'array',
            'partners' => 'array',
            'secondary_activities' => 'array',
            'enrichment_data' => 'array',
            'latitude' => 'float',
            'longitude' => 'float',
            'document' => 'encrypted',
        ];
    }

    public function getCityAttribute(): ?string
    {
        return $this->attributes['address_city'] ?? null;
    }

    public function setCityAttribute(?string $value): void
    {
        $this->attributes['address_city'] = $value;
    }

    public function getStateAttribute(): ?string
    {
        return $this->attributes['address_state'] ?? null;
    }

    public function setStateAttribute(?string $value): void
    {
        $this->attributes['address_state'] = $value;
    }

    public const SOURCES = [
        'indicacao' => 'Indicação',
        'google' => 'Google',
        'instagram' => 'Instagram',
        'feira' => 'Feira',
        'presenca_fisica' => 'Presença Física',
        'outro' => 'Outro',
    ];

    public const SEGMENTS = [
        'supermercado' => 'Supermercado',
        'farmacia' => 'Farmácia',
        'industria' => 'Indústria',
        'padaria' => 'Padaria',
        'laboratorio' => 'Laboratório',
        'frigorifico' => 'Frigorífico',
        'restaurante' => 'Restaurante',
        'hospital' => 'Hospital',
        'agronegocio' => 'Agronegócio',
        'outro' => 'Outro',
    ];

    public const COMPANY_SIZES = [
        'micro' => 'Microempresa',
        'pequena' => 'Pequena',
        'media' => 'Média',
        'grande' => 'Grande',
    ];

    public const CONTRACT_TYPES = [
        'avulso' => 'Avulso',
        'contrato_mensal' => 'Contrato Mensal',
        'contrato_anual' => 'Contrato Anual',
    ];

    public const RATINGS = [
        'A' => 'A — Alto Potencial',
        'B' => 'B — Médio Potencial',
        'C' => 'C — Baixo Potencial',
        'D' => 'D — Inativo',
    ];

    // ─── Scopes ─────────────────────────────────────────

    public function scopeNeedsFollowUp($q)
    {
        return $q->whereNotNull('next_follow_up_at')
            ->where('next_follow_up_at', '<=', now());
    }

    public function scopeNoContactSince($q, int $days = 90)
    {
        return $q->where(function ($q) use ($days) {
            $q->whereNull('last_contact_at')
                ->orWhere('last_contact_at', '<', now()->subDays($days));
        });
    }

    public function scopeBySegment($q, string $segment)
    {
        return $q->where('segment', $segment);
    }

    public function scopeByRating($q, string $rating)
    {
        return $q->where('rating', $rating);
    }

    // ─── Health Score ───────────────────────────────────

    public function getHealthScoreBreakdownAttribute(): array
    {
        $scores = [];

        // Calibrações em dia (0-30)
        $equipments = $this->equipments()->active()->get();
        if ($equipments->isEmpty()) {
            $scores['calibracoes'] = ['score' => 30, 'max' => 30, 'label' => 'Calibrações em dia'];
        } else {
            $total = $equipments->count();
            $emDia = $equipments->filter(fn ($e) => $e->calibration_status !== 'vencida')->count();
            $scores['calibracoes'] = [
                'score' => $total > 0 ? (int) round(($emDia / $total) * 30, 0) : 0,
                'max' => 30,
                'label' => 'Calibrações em dia',
            ];
        }

        // OS nos últimos 12 meses (0-20)
        $osRecente = $this->workOrders()
            ->where('created_at', '>=', now()->subMonths(12))
            ->exists();
        $scores['os_recente'] = [
            'score' => $osRecente ? 20 : 0,
            'max' => 20,
            'label' => 'OS nos últimos 12 meses',
        ];

        // Último contato < 90 dias (0-15)
        $contatoRecente = $this->last_contact_at && $this->last_contact_at->diffInDays(now()) < 90;
        $scores['contato_recente'] = [
            'score' => $contatoRecente ? 15 : 0,
            'max' => 15,
            'label' => 'Contato recente (< 90d)',
        ];

        // Orçamento aprovado recente (0-15)
        $orcAprovado = $this->quotes()
            ->whereIn('status', [Quote::STATUS_APPROVED, Quote::STATUS_INVOICED])
            ->where('approved_at', '>=', now()->subMonths(6))
            ->exists();
        $scores['orcamento_aprovado'] = [
            'score' => $orcAprovado ? 15 : 0,
            'max' => 15,
            'label' => 'Orçamento aprovado (< 6m)',
        ];

        // Sem pendências (0-10)
        $temPendencia = $this->accountsReceivable()
            ->where('status', AccountReceivable::STATUS_OVERDUE)
            ->exists();
        $scores['sem_pendencia'] = [
            'score' => $temPendencia ? 0 : 10,
            'max' => 10,
            'label' => 'Sem pendências financeiras',
        ];

        // Volume de equipamentos (0-10)
        $eqCount = $equipments->count();
        $scores['volume_equipamentos'] = [
            'score' => min(10, $eqCount * 2),
            'max' => 10,
            'label' => 'Volume de equipamentos',
        ];

        return $scores;
    }

    public function recalculateHealthScore(): int
    {
        $breakdown = $this->health_score_breakdown;
        $total = collect($breakdown)->sum('score');
        $this->updateQuietly(['health_score' => $total]);

        return $total;
    }

    // ─── Relationships ──────────────────────────────────

    public function contacts(): HasMany
    {
        return $this->hasMany(CustomerContact::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class);
    }

    public function deals(): HasMany
    {
        return $this->hasMany(CrmDeal::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(CrmActivity::class)->orderByDesc('created_at');
    }

    public function assignedSeller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_seller_id');
    }

    public function equipments(): HasMany
    {
        return $this->hasMany(Equipment::class);
    }

    /**
     * GAP-26: Computed — próxima calibração mais urgente entre todos os equipamentos do cliente.
     */
    public function getNearestCalibrationAtAttribute(): ?string
    {
        return $this->equipments()
            ->whereNotNull('next_calibration_at')
            ->min('next_calibration_at');
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }

    public function recurringContracts(): HasMany
    {
        return $this->hasMany(RecurringContract::class);
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    public function serviceCalls(): HasMany
    {
        return $this->hasMany(ServiceCall::class);
    }

    public function accountsReceivable(): HasMany
    {
        return $this->hasMany(AccountReceivable::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(CustomerDocument::class);
    }

    public function complaints(): HasMany
    {
        return $this->hasMany(CustomerComplaint::class);
    }

    public function rfmScores(): HasMany
    {
        return $this->hasMany(CustomerRfmScore::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(CustomerLocation::class);
    }

    public function emails(): HasMany
    {
        return $this->hasMany(Email::class);
    }

    // ─── Import Support ─────────────────────────────────────

    public static function getImportFields(): array
    {
        return [
            ['key' => 'name', 'label' => 'Nome', 'required' => true],
            ['key' => 'document', 'label' => 'CPF/CNPJ', 'required' => true],
            ['key' => 'type', 'label' => 'Tipo (PF/PJ)', 'required' => false],
            ['key' => 'trade_name', 'label' => 'Nome Fantasia', 'required' => false],
            ['key' => 'email', 'label' => 'E-mail', 'required' => false],
            ['key' => 'phone', 'label' => 'Telefone', 'required' => false],
            ['key' => 'phone2', 'label' => 'Telefone 2', 'required' => false],
            ['key' => 'address_zip', 'label' => 'CEP', 'required' => false],
            ['key' => 'address_street', 'label' => 'Rua', 'required' => false],
            ['key' => 'address_number', 'label' => 'Número', 'required' => false],
            ['key' => 'address_complement', 'label' => 'Complemento', 'required' => false],
            ['key' => 'address_neighborhood', 'label' => 'Bairro', 'required' => false],
            ['key' => 'address_city', 'label' => 'Cidade', 'required' => false],
            ['key' => 'address_state', 'label' => 'UF', 'required' => false],
            ['key' => 'notes', 'label' => 'Observações', 'required' => false],
        ];
    }
}
