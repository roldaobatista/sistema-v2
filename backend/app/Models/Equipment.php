<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int|null $customer_id
 * @property int|null $equipment_model_id
 * @property int|null $responsible_user_id
 * @property string|null $serial_number
 * @property string|null $type
 * @property string|null $brand
 * @property string|null $model
 * @property float|null $capacity
 * @property float|null $resolution
 * @property float|null $purchase_value
 * @property string|null $inmetro_number
 * @property string|null $certificate_number
 * @property string|null $tag
 * @property string|null $qr_code
 * @property string|null $photo_url
 * @property bool $is_critical
 * @property bool $is_active
 * @property string|null $notes
 * @property int|null $calibration_interval_months
 * @property \Illuminate\Support\Carbon|null $purchase_date
 * @property \Illuminate\Support\Carbon|null $warranty_expires_at
 * @property \Illuminate\Support\Carbon|null $last_calibration_at
 * @property \Illuminate\Support\Carbon|null $next_calibration_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Customer|null $customer
 * @property-read User|null $responsible
 * @property-read EquipmentModel|null $equipmentModel
 * @property-read Collection<int, EquipmentCalibration> $calibrations
 * @property-read Collection<int, EquipmentMaintenance> $maintenances
 * @property-read Collection<int, EquipmentDocument> $documents
 * @property-read Collection<int, WorkOrder> $workOrders
 */
class Equipment extends Model
{
    use Auditable, BelongsToTenant, HasFactory, SoftDeletes;

    protected $table = 'equipments';

    const CATEGORIES = [
        'balanca_analitica' => 'Balança Analítica',
        'balanca_semi_analitica' => 'Balança Semi-Analítica',
        'balanca_plataforma' => 'Balança de Plataforma',
        'balanca_rodoviaria' => 'Balança Rodoviária',
        'balanca_contadora' => 'Balança Contadora',
        'balanca_precisao' => 'Balança de Precisão',
        'massa_padrao' => 'Massa Padrão',
        'termometro' => 'Termômetro',
        'paquimetro' => 'Paquímetro',
        'micrometro' => 'Micrômetro',
        'manometro' => 'Manômetro',
        'outro' => 'Outro',
    ];

    const PRECISION_CLASSES = [
        'I' => 'Classe I (Especial)',
        'II' => 'Classe II (Fina)',
        'III' => 'Classe III (Média)',
        'IIII' => 'Classe IIII (Ordinária)',
    ];

    public const STATUS_ACTIVE = 'active';

    public const STATUS_IN_CALIBRATION = 'in_calibration';

    public const STATUS_IN_MAINTENANCE = 'in_maintenance';

    public const STATUS_OUT_OF_SERVICE = 'out_of_service';

    public const STATUS_DISCARDED = 'discarded';

    const STATUSES = [
        self::STATUS_ACTIVE => 'Ativo',
        self::STATUS_IN_CALIBRATION => 'Em Calibração',
        self::STATUS_IN_MAINTENANCE => 'Em Manutenção',
        self::STATUS_OUT_OF_SERVICE => 'Fora de Uso',
        self::STATUS_DISCARDED => 'Descartado',
    ];

    protected $fillable = [
        'tenant_id', 'customer_id', 'code', 'type', 'category', 'brand',
        'manufacturer', 'model', 'serial_number', 'capacity', 'capacity_unit',
        'equipment_model_id',
        'resolution', 'precision_class', 'status', 'location',
        'responsible_user_id', 'purchase_date', 'purchase_value',
        'warranty_expires_at', 'last_calibration_at', 'next_calibration_at',
        'calibration_interval_months', 'inmetro_number', 'certificate_number',
        'tag', 'qr_code', 'photo_url', 'is_critical', 'is_active', 'notes',
    ];

    protected $appends = ['calibration_status', 'tracking_url'];

    protected function casts(): array
    {
        return [
            'capacity' => 'decimal:4',
            'resolution' => 'decimal:6',
            'purchase_value' => 'decimal:2',
            'purchase_date' => 'date',
            'warranty_expires_at' => 'date',
            'last_calibration_at' => 'date',
            'next_calibration_at' => 'date',
            'calibration_interval_months' => 'integer',
            'is_critical' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    // ─── Scopes ─────────────────────────────────────────────

    public function scopeCalibrationDue($q, int $days = 30)
    {
        return $q->whereNotNull('next_calibration_at')
            ->where('next_calibration_at', '<=', now()->addDays($days));
    }

    public function scopeOverdue($q)
    {
        return $q->whereNotNull('next_calibration_at')
            ->where('next_calibration_at', '<', now());
    }

    public function scopeCritical($q)
    {
        return $q->where('is_critical', true);
    }

    public function scopeByCategory($q, string $cat)
    {
        return $q->where('category', $cat);
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true)->where('status', '!=', self::STATUS_DISCARDED);
    }

    // ─── Accessors ──────────────────────────────────────────

    public function getCalibrationStatusAttribute(): string
    {
        // Proteção contra select parcial (eager load com :id,type,brand,model)
        if (! array_key_exists('next_calibration_at', $this->attributes)) {
            return 'sem_data';
        }

        $next = $this->attributes['next_calibration_at'];
        if (! $next) {
            return 'sem_data';
        }

        $carbonNext = ($next instanceof Carbon) ? $next : Carbon::parse($next);

        if ($carbonNext->isPast()) {
            return 'vencida';
        }
        if (now()->diffInDays($carbonNext) <= 30) {
            return 'vence_em_breve';
        }

        return 'em_dia';
    }

    public function getTrackingUrlAttribute(): string
    {
        if (! array_key_exists('id', $this->attributes)) {
            return '';
        }

        return config('app.url').'/portal/equipamentos/'.$this->id;
    }

    // ─── Methods ────────────────────────────────────────────

    public function scheduleNextCalibration(): void
    {
        if ($this->calibration_interval_months && $this->last_calibration_at) {
            $last = $this->last_calibration_at;
            $carbonLast = ($last instanceof Carbon) ? $last->copy() : Carbon::parse($last);

            $this->next_calibration_at = $carbonLast->addMonths($this->calibration_interval_months);
            $this->save();
        }
    }

    public static function generateCode(int $tenantId): string
    {
        // Usa withoutGlobalScope('tenant') para garantir que podemos encontrar a sequência
        // mesmo que o tenantId solicitado seja diferente do tenant atual da request (current_tenant_id).
        $sequence = NumberingSequence::withoutGlobalScope('tenant')->firstOrCreate(
            ['tenant_id' => $tenantId, 'entity' => 'equipment'],
            ['prefix' => 'EQP-', 'next_number' => 1, 'padding' => 5]
        );

        return $sequence->generateNext();
    }

    // ─── Relationships ──────────────────────────────────────

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function calibrations(): HasMany
    {
        return $this->hasMany(EquipmentCalibration::class)->orderByDesc('calibration_date');
    }

    public function maintenances(): HasMany
    {
        return $this->hasMany(EquipmentMaintenance::class)->orderByDesc('created_at');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(EquipmentDocument::class)->orderByDesc('created_at');
    }

    public function equipmentModel(): BelongsTo
    {
        return $this->belongsTo(EquipmentModel::class);
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }

    public function quoteEquipments(): HasMany
    {
        return $this->hasMany(QuoteEquipment::class);
    }

    /**
     * @return BelongsToMany<Quote>
     */
    public function quotes(): BelongsToMany
    {
        return $this->belongsToMany(Quote::class, 'quote_equipments', 'equipment_id', 'quote_id')
            ->withTimestamps()
            ->distinct();
    }

    // ─── Import Support ─────────────────────────────────────

    public static function getImportFields(): array
    {
        return [
            ['key' => 'serial_number', 'label' => 'Nº Série', 'required' => true],
            ['key' => 'customer_document', 'label' => 'CPF/CNPJ Cliente', 'required' => true],
            ['key' => 'type', 'label' => 'Tipo', 'required' => false],
            ['key' => 'brand', 'label' => 'Marca', 'required' => false],
            ['key' => 'model', 'label' => 'Modelo', 'required' => false],
            ['key' => 'notes', 'label' => 'Observações', 'required' => false],
        ];
    }
}
