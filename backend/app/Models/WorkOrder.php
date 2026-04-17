<?php

namespace App\Models;

use App\Enums\AgendaItemStatus;
use App\Enums\WorkOrderStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use App\Traits\SyncsWithAgenda;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string|null $os_number
 * @property string|null $number
 * @property int|null $customer_id
 * @property int|null $equipment_id
 * @property int|null $quote_id
 * @property int|null $service_call_id
 * @property int|null $recurring_contract_id
 * @property int|null $seller_id
 * @property int|null $driver_id
 * @property int|null $branch_id
 * @property int|null $created_by
 * @property int|null $assigned_to
 * @property int|null $parent_id
 * @property int|null $checklist_id
 * @property int|null $sla_policy_id
 * @property int|null $dispatch_authorized_by
 * @property string $status
 * @property string $priority
 * @property string|null $origin_type
 * @property string|null $lead_source
 * @property string|null $service_type
 * @property string|null $description
 * @property string|null $internal_notes
 * @property string|null $technical_report
 * @property string|null $manual_justification
 * @property string|null $cancellation_reason
 * @property string|null $signature_path
 * @property string|null $signature_signer
 * @property string|null $signature_ip
 * @property string|null $agreed_payment_method
 * @property string|null $agreed_payment_notes
 * @property string|null $return_destination
 * @property string|null $delivery_forecast
 * @property string|null $tags
 * @property string|null $photo_checklist
 * @property bool $is_master
 * @property bool $is_warranty
 * @property numeric-string|null $discount
 * @property numeric-string|null $discount_percentage
 * @property numeric-string|null $discount_amount
 * @property numeric-string|null $displacement_value
 * @property numeric-string|null $total
 * @property float|null $start_latitude
 * @property float|null $start_longitude
 * @property float|null $end_latitude
 * @property float|null $end_longitude
 * @property numeric-string|null $total_cost
 * @property numeric-string|null $profit_margin
 * @property float|null $arrival_latitude
 * @property float|null $arrival_longitude
 * @property float|null $checkin_lat
 * @property float|null $checkin_lng
 * @property float|null $checkout_lat
 * @property float|null $checkout_lng
 * @property numeric-string|null $auto_km_calculated
 * @property int|null $displacement_duration_minutes
 * @property int|null $wait_time_minutes
 * @property int|null $service_duration_minutes
 * @property int|null $total_duration_minutes
 * @property int|null $return_duration_minutes
 * @property \Illuminate\Support\Carbon|null $scheduled_date
 * @property \Illuminate\Support\Carbon|null $received_at
 * @property \Illuminate\Support\Carbon|null $started_at
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property \Illuminate\Support\Carbon|null $delivered_at
 * @property \Illuminate\Support\Carbon|null $signature_at
 * @property \Illuminate\Support\Carbon|null $sla_due_at
 * @property \Illuminate\Support\Carbon|null $sla_responded_at
 * @property \Illuminate\Support\Carbon|null $dispatch_authorized_at
 * @property \Illuminate\Support\Carbon|null $displacement_started_at
 * @property \Illuminate\Support\Carbon|null $displacement_arrived_at
 * @property bool|null $is_paused
 * @property \Illuminate\Support\Carbon|null $paused_at
 * @property string|null $pause_reason
 * @property bool|null $sla_response_breached
 * @property bool|null $sla_resolution_breached
 * @property bool|null $auto_assigned
 * @property int|null $eta_minutes
 * @property int|null $reschedule_count
 * @property int|null $visit_number
 * @property int|null $reopen_count
 * @property int|null $sla_hours
 * @property \Illuminate\Support\Carbon|null $return_started_at
 * @property \Illuminate\Support\Carbon|null $return_arrived_at
 * @property \Illuminate\Support\Carbon|null $sla_deadline
 * @property \Illuminate\Support\Carbon|null $service_started_at
 * @property \Illuminate\Support\Carbon|null $checkin_at
 * @property \Illuminate\Support\Carbon|null $checkout_at
 * @property \Illuminate\Support\Carbon|null $cancelled_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read string $business_number
 * @property-read string|null $waze_link
 * @property-read string|null $google_maps_link
 * @property-read float|null $estimated_profit
 * @property-read bool $is_under_warranty
 * @property-read \Illuminate\Support\Carbon|null $warranty_until
 * @property-read Customer|null $customer
 * @property-read User|null $creator
 * @property-read User|null $assignee
 * @property-read User|null $seller
 * @property-read User|null $driver
 * @property-read Equipment|null $equipment
 * @property-read Quote|null $quote
 * @property-read ServiceCall|null $serviceCall
 * @property-read WorkOrder|null $parent
 * @property-read Branch|null $branch
 * @property-read \Illuminate\Database\Eloquent\Collection<int, WorkOrder> $children
 * @property-read \Illuminate\Database\Eloquent\Collection<int, WorkOrderItem> $items
 * @property-read \Illuminate\Database\Eloquent\Collection<int, User> $technicians
 * @property-read \Illuminate\Database\Eloquent\Collection<int, WorkOrderStatusHistory> $statusHistory
 * @property-read \Illuminate\Database\Eloquent\Collection<int, WorkOrderAttachment> $attachments
 * @property-read \Illuminate\Database\Eloquent\Collection<int, WorkOrderChat> $chats
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Invoice> $invoices
 * @property-read \Illuminate\Database\Eloquent\Collection<int, AccountPayable> $accountsPayable
 * @property-read \Illuminate\Database\Eloquent\Collection<int, AccountReceivable> $accountsReceivable
 * @property bool|null $requires_adjustment
 * @property bool|null $requires_maintenance
 * @property bool|null $client_wants_conformity_declaration
 * @property bool|null $subject_to_legal_metrology
 * @property bool|null $needs_ipem_interaction
 * @property bool|null $will_emit_complementary_report
 * @property \Illuminate\Support\Carbon|null $client_accepted_at
 */
class WorkOrder extends Model
{
    use Auditable, BelongsToTenant, HasFactory, SoftDeletes, SyncsWithAgenda;

    protected $appends = [
        'business_number',
        'waze_link',
        'google_maps_link',
    ];

    protected $fillable = [
        'tenant_id', 'os_number', 'number', 'customer_id', 'equipment_id',
        'quote_id', 'service_call_id', 'recurring_contract_id', 'seller_id', 'driver_id', 'origin_type', 'lead_source',
        'branch_id', 'created_by', 'assigned_to',
        'status', 'priority', 'description', 'internal_notes', 'technical_report',
        'scheduled_date', 'received_at', 'started_at', 'completed_at', 'delivered_at',
        'discount', 'discount_percentage', 'discount_amount', 'displacement_value', 'total',
        'signature_path', 'signature_signer', 'signature_at', 'signature_ip',
        'checklist_id', 'sla_policy_id', 'sla_due_at', 'sla_responded_at',
        'dispatch_authorized_by', 'dispatch_authorized_at',
        'parent_id', 'is_master', 'is_warranty',
        'displacement_started_at', 'displacement_arrived_at', 'displacement_duration_minutes',
        'service_started_at', 'wait_time_minutes', 'service_duration_minutes', 'total_duration_minutes',
        'arrival_latitude', 'arrival_longitude',
        'return_started_at', 'return_arrived_at', 'return_duration_minutes', 'return_destination',
        'checkin_at', 'checkin_lat', 'checkin_lng', 'checkout_at', 'checkout_lat', 'checkout_lng', 'auto_km_calculated',
        'service_type', 'manual_justification',
        'cancelled_at', 'cancellation_reason',
        'agreed_payment_method', 'agreed_payment_notes',
        'delivery_forecast', 'tags', 'photo_checklist',
        'address', 'city', 'state', 'zip_code', 'contact_phone',
        'project_id', 'fleet_vehicle_id', 'cost_center_id',
        'is_paused', 'paused_at', 'pause_reason',
        'cancellation_category',
        'difficulty_level', 'eta_minutes',
        'start_latitude', 'start_longitude', 'end_latitude', 'end_longitude',
        'total_cost', 'profit_margin',
        'reschedule_count', 'visit_number', 'reopen_count',
        'sla_response_breached', 'sla_resolution_breached',
        'sla_deadline', 'sla_hours',
        'auto_assigned', 'auto_assignment_rule_id',
        'rating_token',
        // Análise Crítica (ISO 17025 / Calibração)
        'service_modality', 'requires_adjustment', 'requires_maintenance',
        'client_wants_conformity_declaration', 'decision_rule_agreed',
        'subject_to_legal_metrology', 'needs_ipem_interaction',
        'site_conditions', 'calibration_scope_notes', 'will_emit_complementary_report',
        // Normative compliance fields
        'client_accepted_at', 'client_accepted_by', 'applicable_procedure',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_date' => 'datetime',
            'received_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'delivered_at' => 'datetime',
            'signature_at' => 'datetime',
            'sla_due_at' => 'datetime',
            'sla_responded_at' => 'datetime',
            'displacement_started_at' => 'datetime',
            'displacement_arrived_at' => 'datetime',
            'service_started_at' => 'datetime',
            'return_started_at' => 'datetime',
            'return_arrived_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'dispatch_authorized_at' => 'datetime',
            'delivery_forecast' => 'date',
            'discount' => 'decimal:2',
            'discount_percentage' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'displacement_value' => 'decimal:2',
            'total' => 'decimal:2',
            'tags' => 'array',
            'photo_checklist' => 'array',
            'arrival_latitude' => 'float',
            'arrival_longitude' => 'float',
            'checkin_at' => 'datetime',
            'checkin_lat' => 'float',
            'checkin_lng' => 'float',
            'checkout_at' => 'datetime',
            'checkout_lat' => 'float',
            'checkout_lng' => 'float',
            'auto_km_calculated' => 'decimal:2',
            'displacement_duration_minutes' => 'integer',
            'wait_time_minutes' => 'integer',
            'service_duration_minutes' => 'integer',
            'total_duration_minutes' => 'integer',
            'return_duration_minutes' => 'integer',
            'is_master' => 'boolean',
            'is_warranty' => 'boolean',
            'is_paused' => 'boolean',
            'paused_at' => 'datetime',
            'sla_response_breached' => 'boolean',
            'sla_resolution_breached' => 'boolean',
            'sla_deadline' => 'datetime',
            'auto_assigned' => 'boolean',
            'requires_adjustment' => 'boolean',
            'requires_maintenance' => 'boolean',
            'client_wants_conformity_declaration' => 'boolean',
            'subject_to_legal_metrology' => 'boolean',
            'needs_ipem_interaction' => 'boolean',
            'will_emit_complementary_report' => 'boolean',
            'client_accepted_at' => 'datetime',
            'start_latitude' => 'float',
            'start_longitude' => 'float',
            'end_latitude' => 'float',
            'end_longitude' => 'float',
            'total_cost' => 'decimal:2',
            'profit_margin' => 'decimal:2',
            'eta_minutes' => 'integer',
            'reschedule_count' => 'integer',
            'visit_number' => 'integer',
            'reopen_count' => 'integer',
            'sla_hours' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $workOrder): void {
            $workOrder->os_number = self::sanitizeOsNumber($workOrder->os_number);

            if (! $workOrder->os_number && $workOrder->number) {
                // Backward compatibility for integrations that do not pass os_number.
                $workOrder->os_number = $workOrder->number;
            }

            // Auto-atribuir SLA policy baseado na priority
            if (! $workOrder->sla_policy_id && $workOrder->tenant_id) {
                $slaPolicy = SlaPolicy::where('tenant_id', $workOrder->tenant_id)
                    ->where('is_active', true)
                    ->where('priority', $workOrder->priority ?? 'normal')
                    ->first();

                if ($slaPolicy) {
                    $workOrder->sla_policy_id = $slaPolicy->id;
                    $workOrder->sla_due_at = now()->addMinutes($slaPolicy->resolution_time_minutes);
                }
            }
        });

        static::updating(function (self $workOrder): void {
            if ($workOrder->isDirty('os_number')) {
                $workOrder->os_number = self::sanitizeOsNumber($workOrder->os_number);
            }
        });

        static::deleting(function (self $workOrder): void {
            // Limpar arquivos de photo_checklist do Storage
            $checklist = $workOrder->photo_checklist;
            if (is_array($checklist)) {
                $paths = [];
                foreach (['before', 'during', 'after'] as $step) {
                    foreach ($checklist[$step] ?? [] as $entry) {
                        if (! empty($entry['path'])) {
                            $paths[] = $entry['path'];
                        }
                    }
                }
                foreach ($checklist['items'] ?? [] as $item) {
                    if (! empty($item['photo_url'])) {
                        $paths[] = $item['photo_url'];
                    }
                }
                foreach ($paths as $path) {
                    Storage::disk('public')->delete($path);
                }
            }
        });
    }

    public function businessNumber(): Attribute
    {
        return Attribute::get(fn (): string => (string) ($this->os_number ?: $this->number));
    }

    public function wazeLink(): Attribute
    {
        return Attribute::get(function () {
            if (! $this->relationLoaded('customer') || ! $this->customer) {
                return null;
            }

            $attrs = $this->customer->getAttributes();
            if (! array_key_exists('latitude', $attrs) || ! array_key_exists('longitude', $attrs)) {
                return null;
            }

            $lat = $this->customer->latitude;
            $lng = $this->customer->longitude;
            if (! $lat || ! $lng) {
                return null;
            }

            return "waze://?ll={$lat},{$lng}&navigate=yes";
        });
    }

    public function googleMapsLink(): Attribute
    {
        return Attribute::get(function () {
            if (! $this->relationLoaded('customer') || ! $this->customer) {
                return null;
            }

            $attrs = $this->customer->getAttributes();
            if (! array_key_exists('latitude', $attrs) || ! array_key_exists('longitude', $attrs)) {
                return null;
            }

            $lat = $this->customer->latitude;
            $lng = $this->customer->longitude;
            if (! $lat || ! $lng) {
                return null;
            }

            return "https://www.google.com/maps/search/?api=1&query={$lat},{$lng}";
        });
    }

    // ── Status (via Enum — constantes mantidas para backward compat) ──
    /** @deprecated Use WorkOrderStatus::OPEN */
    public const STATUS_OPEN = 'open';

    /** @deprecated Legacy mobile status kept only for backward compatibility */
    public const STATUS_PENDING = 'pending';

    /** @deprecated Use WorkOrderStatus::AWAITING_DISPATCH */
    public const STATUS_AWAITING_DISPATCH = 'awaiting_dispatch';

    /** @deprecated Use WorkOrderStatus::IN_DISPLACEMENT */
    public const STATUS_IN_DISPLACEMENT = 'in_displacement';

    /** @deprecated Use WorkOrderStatus::DISPLACEMENT_PAUSED */
    public const STATUS_DISPLACEMENT_PAUSED = 'displacement_paused';

    /** @deprecated Use WorkOrderStatus::AT_CLIENT */
    public const STATUS_AT_CLIENT = 'at_client';

    /** @deprecated Use WorkOrderStatus::IN_SERVICE */
    public const STATUS_IN_SERVICE = 'in_service';

    /** @deprecated Use WorkOrderStatus::SERVICE_PAUSED */
    public const STATUS_SERVICE_PAUSED = 'service_paused';

    /** @deprecated Use WorkOrderStatus::AWAITING_RETURN */
    public const STATUS_AWAITING_RETURN = 'awaiting_return';

    /** @deprecated Use WorkOrderStatus::IN_RETURN */
    public const STATUS_IN_RETURN = 'in_return';

    /** @deprecated Use WorkOrderStatus::RETURN_PAUSED */
    public const STATUS_RETURN_PAUSED = 'return_paused';

    /** @deprecated Use WorkOrderStatus::WAITING_PARTS */
    public const STATUS_WAITING_PARTS = 'waiting_parts';

    /** @deprecated Use WorkOrderStatus::WAITING_APPROVAL */
    public const STATUS_WAITING_APPROVAL = 'waiting_approval';

    /** @deprecated Use WorkOrderStatus::COMPLETED */
    public const STATUS_COMPLETED = 'completed';

    /** @deprecated Use WorkOrderStatus::DELIVERED */
    public const STATUS_DELIVERED = 'delivered';

    /** @deprecated Use WorkOrderStatus::INVOICED */
    public const STATUS_INVOICED = 'invoiced';

    /** @deprecated Use WorkOrderStatus::CANCELLED */
    public const STATUS_CANCELLED = 'cancelled';

    /** @deprecated Use WorkOrderStatus::IN_PROGRESS */
    public const STATUS_IN_PROGRESS = 'in_progress';

    // ── Origin Types ──
    public const ORIGIN_QUOTE = 'quote';

    public const ORIGIN_SERVICE_CALL = 'service_call';

    public const ORIGIN_RECURRING = 'recurring_contract';

    public const ORIGIN_MANUAL = 'manual';

    // ── Lead Sources (Commercial Origin — affects commission %) ──
    public const LEAD_SOURCES = [
        'prospeccao' => 'Prospecção',
        'retorno' => 'Retorno',
        'contato_direto' => 'Contato Direto',
        'indicacao' => 'Indicação',
    ];

    // ── Priority Constants ──
    public const PRIORITY_LOW = 'low';

    public const PRIORITY_NORMAL = 'normal';

    public const PRIORITY_HIGH = 'high';

    public const PRIORITY_URGENT = 'urgent';

    /** @return array<string, array{label: string, color: string}> */
    public static function statuses(): array
    {
        return collect(WorkOrderStatus::cases())
            ->mapWithKeys(fn (WorkOrderStatus $s) => [$s->value => ['label' => $s->label(), 'color' => $s->color()]])
            ->all();
    }

    /** @deprecated Use WorkOrderStatus::cases() or self::statuses() */
    public const STATUSES = [
        self::STATUS_OPEN => ['label' => 'Aberta', 'color' => 'info'],
        self::STATUS_AWAITING_DISPATCH => ['label' => 'Aguard. Despacho', 'color' => 'amber'],
        self::STATUS_IN_DISPLACEMENT => ['label' => 'Em Deslocamento', 'color' => 'cyan'],
        self::STATUS_DISPLACEMENT_PAUSED => ['label' => 'Desloc. Pausado', 'color' => 'amber'],
        self::STATUS_AT_CLIENT => ['label' => 'No Cliente', 'color' => 'info'],
        self::STATUS_IN_SERVICE => ['label' => 'Em Serviço', 'color' => 'warning'],
        self::STATUS_SERVICE_PAUSED => ['label' => 'Serviço Pausado', 'color' => 'amber'],
        self::STATUS_AWAITING_RETURN => ['label' => 'Serviço Concluído', 'color' => 'teal'],
        self::STATUS_IN_RETURN => ['label' => 'Em Retorno', 'color' => 'cyan'],
        self::STATUS_RETURN_PAUSED => ['label' => 'Retorno Pausado', 'color' => 'amber'],
        self::STATUS_WAITING_PARTS => ['label' => 'Aguard. Peças', 'color' => 'warning'],
        self::STATUS_WAITING_APPROVAL => ['label' => 'Aguard. Aprovação', 'color' => 'brand'],
        self::STATUS_COMPLETED => ['label' => 'Finalizada', 'color' => 'success'],
        self::STATUS_DELIVERED => ['label' => 'Entregue', 'color' => 'success'],
        self::STATUS_INVOICED => ['label' => 'Faturada', 'color' => 'brand'],
        self::STATUS_CANCELLED => ['label' => 'Cancelada', 'color' => 'danger'],
        self::STATUS_IN_PROGRESS => ['label' => 'Em Andamento', 'color' => 'warning'],
    ];

    public const PRIORITIES = [
        self::PRIORITY_LOW => ['label' => 'Baixa', 'color' => 'default'],
        self::PRIORITY_NORMAL => ['label' => 'Normal', 'color' => 'info'],
        self::PRIORITY_HIGH => ['label' => 'Alta', 'color' => 'warning'],
        self::PRIORITY_URGENT => ['label' => 'Urgente', 'color' => 'danger'],
    ];

    /** Valor de agreed_payment_method quando o pagamento será combinado após emissão da nota */
    public const AGREED_PAYMENT_PENDING_AFTER_INVOICE = 'pending_after_invoice';

    public const AGREED_PAYMENT_METHODS = [
        'pix' => 'PIX',
        'boleto' => 'Boleto',
        'cartao_credito' => 'Cartão Crédito',
        'cartao_debito' => 'Cartão Débito',
        'transferencia' => 'Transferência',
        'dinheiro' => 'Dinheiro',
        self::AGREED_PAYMENT_PENDING_AFTER_INVOICE => 'A combinar após emissão da nota',
    ];

    /**
     * Allowed status transitions — derived from WorkOrderStatus enum (single source of truth).
     * Kept as a constant for backward compatibility with callers that reference WorkOrder::ALLOWED_TRANSITIONS.
     */
    public const ALLOWED_TRANSITIONS = [
        self::STATUS_OPEN => [self::STATUS_AWAITING_DISPATCH, self::STATUS_IN_DISPLACEMENT, self::STATUS_IN_PROGRESS, self::STATUS_WAITING_APPROVAL, self::STATUS_CANCELLED],
        self::STATUS_AWAITING_DISPATCH => [self::STATUS_IN_DISPLACEMENT, self::STATUS_CANCELLED],
        self::STATUS_IN_DISPLACEMENT => [self::STATUS_DISPLACEMENT_PAUSED, self::STATUS_AT_CLIENT, self::STATUS_CANCELLED],
        self::STATUS_DISPLACEMENT_PAUSED => [self::STATUS_IN_DISPLACEMENT],
        self::STATUS_AT_CLIENT => [self::STATUS_IN_SERVICE, self::STATUS_CANCELLED],
        self::STATUS_IN_SERVICE => [self::STATUS_SERVICE_PAUSED, self::STATUS_WAITING_PARTS, self::STATUS_AWAITING_RETURN, self::STATUS_CANCELLED],
        self::STATUS_SERVICE_PAUSED => [self::STATUS_IN_SERVICE],
        self::STATUS_AWAITING_RETURN => [self::STATUS_IN_RETURN, self::STATUS_COMPLETED],
        self::STATUS_IN_RETURN => [self::STATUS_RETURN_PAUSED, self::STATUS_COMPLETED],
        self::STATUS_RETURN_PAUSED => [self::STATUS_IN_RETURN],
        self::STATUS_WAITING_PARTS => [self::STATUS_IN_SERVICE, self::STATUS_CANCELLED],
        self::STATUS_WAITING_APPROVAL => [self::STATUS_OPEN, self::STATUS_COMPLETED, self::STATUS_CANCELLED],
        self::STATUS_COMPLETED => [self::STATUS_WAITING_APPROVAL, self::STATUS_DELIVERED, self::STATUS_CANCELLED],
        self::STATUS_DELIVERED => [self::STATUS_INVOICED],
        self::STATUS_INVOICED => [],
        self::STATUS_CANCELLED => [self::STATUS_OPEN],
        self::STATUS_IN_PROGRESS => [self::STATUS_WAITING_PARTS, self::STATUS_AWAITING_RETURN, self::STATUS_COMPLETED, self::STATUS_CANCELLED],
    ];

    public function canTransitionTo(string $newStatus): bool
    {
        $currentEnum = WorkOrderStatus::tryFrom($this->status);
        $targetEnum = WorkOrderStatus::tryFrom($newStatus);

        if (! $currentEnum || ! $targetEnum) {
            return false;
        }

        return $currentEnum->canTransitionTo($targetEnum);
    }

    public static function nextNumber(int $tenantId): string
    {
        $cacheKey = "seq_workorder_{$tenantId}";
        $lockKey = "lock_{$cacheKey}";

        return Cache::lock($lockKey, 5)->block(5, function () use ($tenantId, $cacheKey) {
            if (! Cache::has($cacheKey)) {
                $last = static::withTrashed()
                    ->withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->lockForUpdate()
                    ->max('number');
                $seq = $last ? (int) str_replace('OS-', '', $last) : 0;
                Cache::forever($cacheKey, $seq);
            }

            $next = Cache::increment($cacheKey);

            return 'OS-'.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
        });
    }

    public function recalculateTotal(): void
    {
        $totals = $this->calculateFinancialTotals();

        $this->update([
            'total' => $totals['grand_total'],
            'discount_amount' => $totals['global_discount'],
        ]);
    }

    /**
     * Calcula os totais financeiros da OS usando uma unica regra de dominio.
     *
     * Ordem da regra:
     * 1. subtotal bruto dos itens
     * 2. desconto por item
     * 3. subtotal liquido dos itens
     * 4. desconto global percentual ou fixo
     * 5. soma do deslocamento
     * 6. piso em zero no total final
     *
     * @return array{
     *     items: array<int, array<string, mixed>>,
     *     items_subtotal: string,
     *     items_discount: string,
     *     items_net_subtotal: string,
     *     displacement_value: string,
     *     global_discount: string,
     *     grand_total: string
     * }
     */
    public function calculateFinancialTotals(): array
    {
        /** @var Collection<int, WorkOrderItem> $items */
        $items = $this->relationLoaded('items')
            ? $this->items
            : $this->items()->get();

        $itemsSubtotal = '0.00';
        $itemsDiscount = '0.00';
        $itemsNetSubtotal = '0.00';
        $breakdown = [];

        foreach ($items as $item) {
            $lineSubtotal = bcmul((string) ($item->quantity ?? '0'), (string) ($item->unit_price ?? '0'), 2);
            $lineDiscount = (string) ($item->discount ?? '0.00');
            $lineNet = bcsub($lineSubtotal, $lineDiscount, 2);

            if (bccomp($lineNet, '0', 2) < 0) {
                $lineNet = '0.00';
            }

            $itemsSubtotal = bcadd($itemsSubtotal, $lineSubtotal, 2);
            $itemsDiscount = bcadd($itemsDiscount, $lineDiscount, 2);
            $itemsNetSubtotal = bcadd($itemsNetSubtotal, $lineNet, 2);

            $breakdown[] = [
                'id' => $item->id,
                'type' => $item->type,
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'discount' => $item->discount ?? '0.00',
                'line_subtotal' => $lineSubtotal,
                'line_total' => $lineNet,
            ];
        }

        $globalDiscount = (float) $this->discount_percentage > 0
            ? bcmul($itemsNetSubtotal, bcdiv((string) $this->discount_percentage, '100', 4), 2)
            : (string) ($this->discount ?? '0.00');

        $displacement = (string) ($this->displacement_value ?? '0.00');
        $grandTotal = bcsub(bcadd($itemsNetSubtotal, $displacement, 2), $globalDiscount, 2);

        if (bccomp($grandTotal, '0', 2) < 0) {
            $grandTotal = '0.00';
        }

        return [
            'items' => $breakdown,
            'items_subtotal' => $itemsSubtotal,
            'items_discount' => $itemsDiscount,
            'items_net_subtotal' => $itemsNetSubtotal,
            'displacement_value' => $displacement,
            'global_discount' => $globalDiscount,
            'grand_total' => $grandTotal,
        ];
    }

    // ── Named Scopes ──

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotIn('status', [
            WorkOrderStatus::COMPLETED->value,
            WorkOrderStatus::DELIVERED->value,
            WorkOrderStatus::INVOICED->value,
            WorkOrderStatus::CANCELLED->value,
        ]);
    }

    public function scopeByStatus(Builder $query, WorkOrderStatus $status): Builder
    {
        return $query->where('status', $status->value);
    }

    public function scopeByPriority(Builder $query, string $priority): Builder
    {
        return $query->where('priority', $priority);
    }

    public function scopeByAssignee(Builder $query, int $userId): Builder
    {
        return $query->where('assigned_to', $userId);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereIn('status', [
            WorkOrderStatus::OPEN->value,
            WorkOrderStatus::AWAITING_DISPATCH->value,
        ]);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->active()
            ->whereNotNull('sla_due_at')
            ->where('sla_due_at', '<', now());
    }

    // ── Relationships ──

    public function parent(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(WorkOrder::class, 'parent_id');
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function serviceCall(): BelongsTo
    {
        return $this->belongsTo(ServiceCall::class);
    }

    public function recurringContract(): BelongsTo
    {
        return $this->belongsTo(RecurringContract::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function dispatchAuthorizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispatch_authorized_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(WorkOrderItem::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(WorkOrderStatusHistory::class)->orderByDesc('created_at');
    }

    public function checklist(): BelongsTo
    {
        return $this->belongsTo(ServiceChecklist::class);
    }

    public function checklistResponses(): HasMany
    {
        return $this->hasMany(WorkOrderChecklistResponse::class);
    }

    public function slaPolicy(): BelongsTo
    {
        return $this->belongsTo(SlaPolicy::class);
    }

    public function chats(): HasMany
    {
        return $this->hasMany(WorkOrderChat::class)->orderBy('created_at');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function satisfactionSurvey(): HasOne
    {
        return $this->hasOne(SatisfactionSurvey::class);
    }

    public function accountsPayable(): HasMany
    {
        return $this->hasMany(AccountPayable::class);
    }

    public function accountsReceivable(): HasMany
    {
        return $this->hasMany(AccountReceivable::class);
    }

    public function technicians(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'work_order_technicians')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function equipmentsList(): BelongsToMany
    {
        return $this->belongsToMany(Equipment::class, 'work_order_equipments')
            ->withPivot('observations')
            ->withTimestamps();
    }

    public function displacementStops(): HasMany
    {
        return $this->hasMany(WorkOrderDisplacementStop::class)->orderBy('started_at');
    }

    public function displacementLocations(): HasMany
    {
        return $this->hasMany(WorkOrderDisplacementLocation::class)->orderBy('recorded_at');
    }

    public function events(): HasMany
    {
        return $this->hasMany(WorkOrderEvent::class)->orderBy('created_at');
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(WorkOrderRating::class);
    }

    public function timeLogs(): HasMany
    {
        return $this->hasMany(WorkOrderTimeLog::class);
    }

    public function deals(): HasMany
    {
        return $this->hasMany(CrmDeal::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    public const SERVICE_TYPES = [
        'diagnostico' => 'Diagnóstico',
        'manutencao_corretiva' => 'Manutenção Corretiva',
        'preventiva' => 'Preventiva',
        'calibracao' => 'Calibração',
        'instalacao' => 'Instalação',
        'retorno' => 'Retorno',
        'garantia' => 'Garantia',
    ];

    public function isTechnicianAuthorized(?int $userId): bool
    {
        if (! $userId) {
            return false;
        }
        if ((int) $this->assigned_to === $userId) {
            return true;
        }

        return $this->technicians()->where('user_id', $userId)->exists();
    }

    // GAP-23: Configurable warranty days (no hardcode)
    public static function warrantyDays(?int $tenantId = null): int
    {
        if ($tenantId) {
            $val = SystemSetting::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('key', 'warranty_days')
                ->value('value');
            if ($val !== null) {
                return max(0, (int) $val);
            }
        }

        return 90; // Fallback only
    }

    public function getWarrantyUntilAttribute(): ?Carbon
    {
        if (! $this->completed_at) {
            return null;
        }

        return $this->completed_at->copy()->addDays(self::warrantyDays($this->tenant_id));
    }

    public function getIsUnderWarrantyAttribute(): bool
    {
        return $this->warranty_until && $this->warranty_until->isFuture();
    }

    /**
     * DRE de Lucratividade Dinâmica da OS (3.25)
     * Returns estimated profit: revenue - costs
     */
    public function getEstimatedProfitAttribute(): array
    {
        $revenue = (string) ($this->total ?? '0');

        $items = $this->relationLoaded('items')
            ? $this->items
            : $this->items()->get(['quantity', 'cost_price']);

        $itemsCost = $items->reduce(
            function (string $carry, WorkOrderItem $item): string {
                $lineCost = bcmul(
                    (string) ($item->cost_price ?? '0'),
                    (string) ($item->quantity ?? '0'),
                    2
                );

                return bcadd($carry, $lineCost, 2);
            },
            '0.00'
        );

        // Cost: displacement
        $displacement = (string) ($this->displacement_value ?? '0');

        // Cost: estimated commission (5% of revenue as default)
        $commission = bcmul($revenue, '0.05', 2);

        $totalCost = bcadd(bcadd($itemsCost, $displacement, 2), $commission, 2);
        $profit = bcsub($revenue, $totalCost, 2);
        $margin = bccomp($revenue, '0', 2) > 0
            ? bcmul(bcdiv($profit, $revenue, 4), '100', 1)
            : '0.0';

        return [
            'revenue' => $revenue,
            'costs' => $totalCost,
            'profit' => $profit,
            'margin_pct' => (float) $margin,
            'breakdown' => [
                'items_cost' => $itemsCost,
                'displacement' => $displacement,
                'commission' => $commission,
            ],
        ];
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(WorkOrderAttachment::class);
    }

    public function signatures(): HasMany
    {
        return $this->hasMany(WorkOrderSignature::class);
    }

    public function calibrations(): HasMany
    {
        return $this->hasMany(EquipmentCalibration::class);
    }

    /**
     * @return HasMany<MaintenanceReport, $this>
     */
    public function maintenanceReports(): HasMany
    {
        return $this->hasMany(MaintenanceReport::class);
    }

    public function centralSyncData(): array
    {
        $statusMap = [
            self::STATUS_OPEN => AgendaItemStatus::ABERTO,
            self::STATUS_AWAITING_DISPATCH => AgendaItemStatus::ABERTO,
            self::STATUS_IN_DISPLACEMENT => AgendaItemStatus::EM_ANDAMENTO,
            self::STATUS_DISPLACEMENT_PAUSED => AgendaItemStatus::EM_ANDAMENTO,
            self::STATUS_AT_CLIENT => AgendaItemStatus::EM_ANDAMENTO,
            self::STATUS_IN_SERVICE => AgendaItemStatus::EM_ANDAMENTO,
            self::STATUS_SERVICE_PAUSED => AgendaItemStatus::EM_ANDAMENTO,
            self::STATUS_AWAITING_RETURN => AgendaItemStatus::EM_ANDAMENTO,
            self::STATUS_IN_RETURN => AgendaItemStatus::EM_ANDAMENTO,
            self::STATUS_RETURN_PAUSED => AgendaItemStatus::EM_ANDAMENTO,
            self::STATUS_IN_PROGRESS => AgendaItemStatus::EM_ANDAMENTO,
            self::STATUS_WAITING_PARTS => AgendaItemStatus::EM_ANDAMENTO,
            self::STATUS_WAITING_APPROVAL => AgendaItemStatus::EM_ANDAMENTO,
            self::STATUS_COMPLETED => AgendaItemStatus::CONCLUIDO,
            self::STATUS_DELIVERED => AgendaItemStatus::CONCLUIDO,
            self::STATUS_INVOICED => AgendaItemStatus::CONCLUIDO,
            self::STATUS_CANCELLED => AgendaItemStatus::CANCELADO,
        ];

        $title = "OS #{$this->business_number}";
        if ($this->relationLoaded('customer') && $this->customer) {
            $title .= " - {$this->customer->name}";
        }

        return [
            'status' => $statusMap[$this->status] ?? AgendaItemStatus::ABERTO,
            'title' => $title,
        ];
    }

    private static function sanitizeOsNumber(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }
}
