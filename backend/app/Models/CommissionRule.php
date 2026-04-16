<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use App\Support\Decimal;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property numeric-string|null $value
 * @property bool|null $active
 * @property array<int|string, mixed>|null $tiers
 * @property int|null $priority
 */
class CommissionRule extends Model
{
    use Auditable, BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'user_id', 'name', 'type', 'value', 'applies_to',
        'calculation_type', 'applies_to_role', 'applies_when', 'tiers', 'priority', 'active',
        'source_filter', 'percentage', 'fixed_amount',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'active' => 'boolean',
            'tiers' => 'array',
            'priority' => 'integer',
        ];
    }

    // ── Legacy types ──
    public const TYPE_PERCENTAGE = 'percentage';

    public const TYPE_FIXED = 'fixed';

    // ── Applies to (items) ──
    public const APPLIES_ALL = 'all';

    public const APPLIES_PRODUCTS = 'products';

    public const APPLIES_SERVICES = 'services';

    // ── 10+ Calculation Types ──
    public const CALC_PERCENT_GROSS = 'percent_gross';

    public const CALC_PERCENT_NET = 'percent_net';

    public const CALC_PERCENT_GROSS_MINUS_DISPLACEMENT = 'percent_gross_minus_displacement';

    public const CALC_PERCENT_SERVICES_ONLY = 'percent_services_only';

    public const CALC_PERCENT_PRODUCTS_ONLY = 'percent_products_only';

    public const CALC_FIXED_PER_OS = 'fixed_per_os';

    public const CALC_PERCENT_PROFIT = 'percent_profit';

    public const CALC_PERCENT_GROSS_MINUS_EXPENSES = 'percent_gross_minus_expenses';

    public const CALC_TIERED_GROSS = 'tiered_gross';

    public const CALC_FIXED_PER_ITEM = 'fixed_per_item';

    public const CALC_CUSTOM_FORMULA = 'custom_formula';

    public const CALCULATION_TYPES = [
        self::CALC_PERCENT_GROSS => '% do Bruto',
        self::CALC_PERCENT_NET => '% do Líquido (bruto − despesas)',
        self::CALC_PERCENT_GROSS_MINUS_DISPLACEMENT => '% (Bruto − Deslocamento)',
        self::CALC_PERCENT_SERVICES_ONLY => '% somente Serviços',
        self::CALC_PERCENT_PRODUCTS_ONLY => '% somente Produtos',
        self::CALC_FIXED_PER_OS => 'Fixo por OS',
        self::CALC_PERCENT_PROFIT => '% do Lucro',
        self::CALC_PERCENT_GROSS_MINUS_EXPENSES => '% (Bruto − Despesas OS)',
        self::CALC_TIERED_GROSS => '% Escalonado por faixa',
        self::CALC_FIXED_PER_ITEM => 'Fixo por Item',
        self::CALC_CUSTOM_FORMULA => 'Fórmula Personalizada',
    ];

    // ── Roles ──
    public const ROLE_TECHNICIAN = 'tecnico';

    public const ROLE_SELLER = 'vendedor';

    public const ROLE_DRIVER = 'motorista';

    public const ROLE_ALIASES = [
        'tecnico' => self::ROLE_TECHNICIAN,
        'technician' => self::ROLE_TECHNICIAN,
        'vendedor' => self::ROLE_SELLER,
        'seller' => self::ROLE_SELLER,
        'salesperson' => self::ROLE_SELLER,
        'motorista' => self::ROLE_DRIVER,
        'driver' => self::ROLE_DRIVER,
    ];

    // ── When to trigger ──
    public const WHEN_OS_COMPLETED = 'os_completed';

    public const WHEN_INSTALLMENT_PAID = 'installment_paid';

    public const WHEN_OS_INVOICED = 'os_invoiced';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function normalizeRole(?string $role): ?string
    {
        if ($role === null) {
            return null;
        }

        $normalized = mb_strtolower(trim($role));
        if ($normalized === '') {
            return null;
        }

        return self::ROLE_ALIASES[$normalized] ?? $normalized;
    }

    public static function acceptedRoleValues(): array
    {
        return array_values(array_unique(array_merge(
            array_values(self::ROLE_ALIASES),
            array_keys(self::ROLE_ALIASES),
        )));
    }

    public static function aliasesForRole(string $role): array
    {
        $canonical = self::normalizeRole($role);

        return array_values(array_unique(array_filter(
            array_keys(self::ROLE_ALIASES),
            fn (string $alias) => self::ROLE_ALIASES[$alias] === $canonical
        )));
    }

    public function events(): HasMany
    {
        return $this->hasMany(CommissionEvent::class);
    }

    /**
     * Calcula comissão baseado no calculation_type.
     * $context deve conter: gross, expenses, displacement, products_total, services_total, cost
     */
    public function calculateCommission(float|string $baseAmount, array $context = []): string
    {
        $pct = Decimal::string($this->value);
        $base = Decimal::string($baseAmount);
        $pctDecimal = bcdiv($pct, '100', 6);

        return match ($this->calculation_type) {
            self::CALC_PERCENT_GROSS => bcmul($base, $pctDecimal, 2),

            self::CALC_PERCENT_NET => bcmul(
                bcsub(bcsub($base, Decimal::string($context['expenses'] ?? null), 2), Decimal::string($context['cost'] ?? null), 2),
                $pctDecimal, 2
            ),

            self::CALC_PERCENT_GROSS_MINUS_DISPLACEMENT => bcmul(
                bcsub($base, Decimal::string($context['displacement'] ?? null), 2),
                $pctDecimal, 2
            ),

            self::CALC_PERCENT_SERVICES_ONLY => bcmul(
                Decimal::string($context['services_total'] ?? null), $pctDecimal, 2
            ),

            self::CALC_PERCENT_PRODUCTS_ONLY => bcmul(
                Decimal::string($context['products_total'] ?? null), $pctDecimal, 2
            ),

            self::CALC_FIXED_PER_OS => Decimal::string($this->value),

            self::CALC_FIXED_PER_ITEM => bcmul(
                Decimal::string($this->value),
                Decimal::string($context['items_count'] ?? 1),
                2
            ),

            self::CALC_PERCENT_PROFIT => bcmul(
                bcsub($base, Decimal::string($context['cost'] ?? null), 2),
                $pctDecimal, 2
            ),

            self::CALC_PERCENT_GROSS_MINUS_EXPENSES => bcmul(
                bcsub($base, Decimal::string($context['expenses'] ?? null), 2),
                $pctDecimal, 2
            ),

            self::CALC_TIERED_GROSS => (string) $this->calculateTiered($baseAmount),

            self::CALC_CUSTOM_FORMULA => (string) $this->calculateCustom($baseAmount, $context),

            // Fallback to legacy
            default => $this->type === self::TYPE_PERCENTAGE
                ? bcmul($base, $pctDecimal, 2)
                : Decimal::string($this->value),
        };
    }

    private function calculateTiered(float|string $amount): string
    {
        $tiers = $this->tiers ?? [];
        $commission = '0';
        $remaining = Decimal::string($amount);

        // tiers format: [{ "up_to": 5000, "percent": 5 }, { "up_to": 10000, "percent": 8 }, { "up_to": null, "percent": 10 }]
        $prev = '0';
        foreach ($tiers as $tier) {
            $upTo = isset($tier['up_to']) ? Decimal::string($tier['up_to']) : '99999999999';
            $rangeSize = bcsub($upTo, $prev, 2);
            $rangeAmount = bccomp($remaining, $rangeSize, 2) <= 0 ? $remaining : $rangeSize;
            if (bccomp($rangeAmount, '0', 2) <= 0) {
                break;
            }
            $pctDecimal = bcdiv(Decimal::string($tier['percent'] ?? null), '100', 6);
            $commission = bcadd($commission, bcmul($rangeAmount, $pctDecimal, 2), 2);
            $remaining = bcsub($remaining, $rangeAmount, 2);
            $prev = $upTo;
        }

        return $commission;
    }

    private function calculateCustom(float|string $amount, array $context): string
    {
        $formula = $this->tiers['formula'] ?? null;
        if (! is_string($formula) || trim($formula) === '') {
            $pctDecimal = bcdiv(Decimal::string($this->value), '100', 6);

            return bcmul(Decimal::string($amount), $pctDecimal, 2);
        }

        // Replace variables in formula
        $vars = [
            'gross' => Decimal::string($context['gross'] ?? $amount),
            'net' => bcsub(Decimal::string($context['gross'] ?? $amount), Decimal::string($context['expenses'] ?? null), 2),
            'products' => Decimal::string($context['products_total'] ?? null),
            'services' => Decimal::string($context['services_total'] ?? null),
            'expenses' => Decimal::string($context['expenses'] ?? null),
            'displacement' => Decimal::string($context['displacement'] ?? null),
            'cost' => Decimal::string($context['cost'] ?? null),
            'percent' => Decimal::string($this->value),
        ];

        $expr = $formula;
        // Sort by key length descending to avoid partial replacement (e.g., "cost" inside "displacement_cost")
        uksort($vars, fn ($a, $b) => strlen($b) - strlen($a));
        foreach ($vars as $key => $val) {
            $expr = preg_replace('/\b'.preg_quote($key, '/').'\b/', (string) $val, $expr);
        }

        // Sanitize: only allow numbers, decimals, and math operators
        $expr = preg_replace('/[^0-9.+\-*\/() ]/', '', $expr);
        if (empty(trim($expr))) {
            return '0.00';
        }

        try {
            $result = self::safeEvaluate($expr);
            $clamped = bccomp($result, '0', 2) < 0 ? '0' : $result;

            return bcadd('0', $clamped, 2);
        } catch (\Throwable) {
            $pctDecimal = bcdiv(Decimal::string($this->value), '100', 6);

            return bcmul(Decimal::string($amount), $pctDecimal, 2);
        }
    }

    /**
     * Safe arithmetic expression evaluator (no eval) — uses bcmath for precision.
     * Supports: +, -, *, /, parentheses, decimal numbers.
     */
    /**
     * @return numeric-string
     */
    private static function safeEvaluate(string $expr): string
    {
        // Tokenize
        $tokens = [];
        preg_match_all('/(\d+\.?\d*|[+\-*\/()])/i', $expr, $matches);
        $tokens = $matches[0];

        if (empty($tokens)) {
            return '0';
        }

        $pos = 0;

        return self::parseExpression($tokens, $pos);
    }

    /**
     * @param  list<string>  $tokens
     * @return numeric-string
     */
    private static function parseExpression(array &$tokens, int &$pos): string
    {
        $result = self::parseTerm($tokens, $pos);

        while ($pos < count($tokens) && in_array($tokens[$pos], ['+', '-'])) {
            $op = $tokens[$pos++];
            $right = self::parseTerm($tokens, $pos);
            $result = $op === '+' ? bcadd($result, $right, 6) : bcsub($result, $right, 6);
        }

        return $result;
    }

    /**
     * @param  list<string>  $tokens
     * @return numeric-string
     */
    private static function parseTerm(array &$tokens, int &$pos): string
    {
        $result = self::parseFactor($tokens, $pos);

        while ($pos < count($tokens) && in_array($tokens[$pos], ['*', '/'])) {
            $op = $tokens[$pos++];
            $right = self::parseFactor($tokens, $pos);
            if ($op === '*') {
                $result = bcmul($result, $right, 6);
            } else {
                $result = bccomp($right, '0', 6) !== 0 ? bcdiv($result, $right, 6) : '0';
            }
        }

        return $result;
    }

    /**
     * @param  list<string>  $tokens
     * @return numeric-string
     */
    private static function parseFactor(array &$tokens, int &$pos): string
    {
        if ($pos >= count($tokens)) {
            return '0';
        }

        if ($tokens[$pos] === '(') {
            $pos++; // skip '('
            $result = self::parseExpression($tokens, $pos);
            if ($pos < count($tokens) && $tokens[$pos] === ')') {
                $pos++; // skip ')'
            }

            return $result;
        }

        return Decimal::string($tokens[$pos++], 6);
    }

    /**
     * Bridge method used by CrmObserver — extracts context from a WorkOrder
     * and delegates to calculateCommission().
     */
    public function calculate(WorkOrder $wo): string
    {
        $productsTotal = $wo->items()->where('type', 'product')->sum('total');
        $servicesTotal = $wo->items()->where('type', 'service')->sum('total');

        // Only expenses that affect net value should be deducted for commission calculation
        $expenses = Expense::where('tenant_id', $wo->tenant_id)
            ->where('work_order_id', $wo->id)
            ->where('affects_net_value', true)
            ->sum('amount');

        return $this->calculateCommission(Decimal::string($wo->total), [
            'gross' => Decimal::string($wo->total),
            'products_total' => Decimal::string($productsTotal),
            'services_total' => Decimal::string($servicesTotal),
            'expenses' => Decimal::string($expenses),
            'displacement' => Decimal::string($wo->displacement_value),
            'cost' => '0',
            'items_count' => (int) $wo->items()->count(),
        ]);
    }
}
