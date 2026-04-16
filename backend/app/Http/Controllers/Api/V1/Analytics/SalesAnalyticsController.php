<?php

namespace App\Http\Controllers\Api\V1\Analytics;

use App\Enums\QuoteStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Analytics\SalesAnalyticsDateRangeRequest;
use App\Models\AccountReceivable;
use App\Models\CrmDeal;
use App\Models\Customer;
use App\Models\Quote;
use App\Support\ApiResponse;
use App\Support\Decimal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SalesAnalyticsController extends Controller
{
    private function tenantId(): int
    {
        $user = auth()->user();

        return (int) ($user->current_tenant_id ?? $user->tenant_id);
    }

    public function quoteRentability(Quote $quote): JsonResponse
    {
        $this->authorize('viewAny', AccountReceivable::class);
        abort_if((int) $quote->tenant_id !== $this->tenantId(), 404);

        try {
            $items = $quote->equipments()->with('items.product', 'items.service')->get();

            $totalRevenue = '0';
            $totalCost = '0';
            $breakdown = [];

            foreach ($items as $equipment) {
                foreach ($equipment->items ?? [] as $item) {
                    $unitPrice = Decimal::string($item->unit_price);
                    $qty = Decimal::string($item->quantity ?? '1');
                    $cost = '0';

                    if ($item->product) {
                        $cost = bcmul(Decimal::string($item->product->cost_price), $qty, 2);
                    }

                    $lineTotal = bcmul($unitPrice, $qty, 2);
                    $totalRevenue = bcadd($totalRevenue, $lineTotal, 2);
                    $totalCost = bcadd($totalCost, $cost, 2);

                    $margin = bccomp($lineTotal, '0', 2) > 0
                        ? bcmul(bcdiv(bcsub($lineTotal, $cost, 2), $lineTotal, 4), '100', 1)
                        : '0';

                    $breakdown[] = [
                        'description' => $item->description ?? $item->product->name ?? 'Item',
                        'quantity' => $qty,
                        'unit_price' => $unitPrice,
                        'cost' => $cost,
                        'margin' => (float) $margin,
                    ];
                }
            }

            $discountAmount = Decimal::string($quote->discount_amount);
            $netRevenue = bcsub($totalRevenue, $discountAmount, 2);
            $profit = bcsub($netRevenue, $totalCost, 2);
            $marginPercent = bccomp($netRevenue, '0', 2) > 0
                ? bcmul(bcdiv($profit, $netRevenue, 4), '100', 1)
                : '0';

            return ApiResponse::data([
                'data' => [
                    'quote_id' => $quote->id,
                    'total_revenue' => $totalRevenue,
                    'discount' => $discountAmount,
                    'net_revenue' => $netRevenue,
                    'total_cost' => $totalCost,
                    'profit' => $profit,
                    'margin_percent' => (float) $marginPercent,
                    'min_acceptable_margin' => 15.0,
                    'is_profitable' => bccomp($profit, '0', 2) > 0,
                    'breakdown' => $breakdown,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('SalesAnalytics quoteRentability failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao calcular rentabilidade.', 500);
        }
    }

    public function followUpQueue(SalesAnalyticsDateRangeRequest $request): JsonResponse
    {
        $this->authorize('viewAny', AccountReceivable::class);

        try {
            $tenantId = $this->tenantId();

            $quotes = Quote::where('tenant_id', $tenantId)
                ->whereIn('status', [
                    QuoteStatus::SENT->value,
                    QuoteStatus::PENDING_INTERNAL_APPROVAL->value,
                    QuoteStatus::INTERNALLY_APPROVED->value,
                ])
                ->with(['customer:id,name,phone,email'])
                ->orderBy('valid_until')
                ->get()
                ->map(function ($q) {
                    $validUntil = $q->valid_until ? Carbon::parse($q->valid_until)->endOfDay() : null;
                    $daysRemaining = $validUntil
                        ? now()->startOfDay()->diffInDays($validUntil->copy()->startOfDay(), false)
                        : null;
                    $quoteNumber = $q->quote_number ?? (string) $q->id;
                    $customer = $q->customer;

                    return [
                        'id' => $q->id,
                        'number' => $quoteNumber,
                        'quote_number' => $quoteNumber,
                        'customer' => $customer,
                        'customer_name' => $customer?->name,
                        'total' => (float) $q->total,
                        'value' => (float) $q->total,
                        'status' => $q->status,
                        'sent_at' => $q->sent_at,
                        'valid_until' => $q->valid_until,
                        'days_remaining' => $daysRemaining,
                        'priority' => match (true) {
                            $daysRemaining !== null && $daysRemaining < 0 => 'expired',
                            $daysRemaining !== null && $daysRemaining <= 3 => 'urgent',
                            $daysRemaining !== null && $daysRemaining <= 7 => 'high',
                            default => 'normal',
                        },
                        'last_activity' => $q->updated_at,
                    ];
                })
                ->sortBy(fn ($item) => match ($item['priority']) {
                    'expired' => 0,
                    'urgent' => 1,
                    'high' => 2,
                    default => 3,
                })
                ->values();

            return ApiResponse::data([
                'data' => $quotes,
                'summary' => [
                    'total' => $quotes->count(),
                    'expired' => $quotes->where('priority', 'expired')->count(),
                    'urgent' => $quotes->where('priority', 'urgent')->count(),
                    'total_value' => round($quotes->sum('total'), 2),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('SalesAnalytics followUpQueue failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao listar fila de follow-up.', 500);
        }
    }

    public function lossReasons(SalesAnalyticsDateRangeRequest $request): JsonResponse
    {
        $this->authorize('viewAny', AccountReceivable::class);

        try {
            $tenantId = $this->tenantId();
            $from = Carbon::parse($request->input('from', now()->subMonths(6)));
            $to = Carbon::parse($request->input('to', now()));

            $reasons = CrmDeal::where('tenant_id', $tenantId)
                ->where('status', 'lost')
                ->whereBetween('lost_at', [$from, $to])
                ->select('lost_reason', DB::raw('COUNT(*) as count'), DB::raw('COALESCE(SUM(value), 0) as total_value'))
                ->groupBy('lost_reason')
                ->orderByDesc('count')
                ->get()
                ->map(function ($r) {
                    return [
                        'reason' => $r->lost_reason ?: 'Não informado',
                        'count' => (int) $r->getAttribute('count'),
                        'total_value' => round((float) $r->getAttribute('total_value'), 2),
                    ];
                });

            $totalLost = $reasons->sum('count');

            return ApiResponse::data([
                'data' => $reasons->map(fn ($r) => array_merge($r, [
                    'percentage' => $totalLost > 0 ? round(($r['count'] / $totalLost) * 100, 1) : 0,
                ])),
                'summary' => [
                    'total_lost' => $totalLost,
                    'total_value_lost' => round($reasons->sum('total_value'), 2),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('SalesAnalytics lossReasons failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao analisar motivos de perda.', 500);
        }
    }

    public function clientSegmentation(SalesAnalyticsDateRangeRequest $request): JsonResponse
    {
        $this->authorize('viewAny', AccountReceivable::class);

        try {
            $tenantId = $this->tenantId();
            $months = min((int) $request->input('months', 12), 36);
            $from = now()->subMonths($months)->startOfDay();
            $to = now()->endOfDay();

            $paymentsByReceivable = DB::table('payments')
                ->where('tenant_id', $tenantId)
                ->where('payable_type', AccountReceivable::class)
                ->whereBetween('payment_date', [$from, $to])
                ->select('payable_id', 'amount')
                ->get()
                ->groupBy('payable_id');

            $receivableIdsWithPeriodPayments = $paymentsByReceivable->keys();

            $receivables = AccountReceivable::query()
                ->where('tenant_id', $tenantId)
                ->whereNotNull('customer_id')
                ->where(function ($query) use ($receivableIdsWithPeriodPayments, $from, $to) {
                    $query->whereIn('id', $receivableIdsWithPeriodPayments)
                        ->orWhere(function ($legacy) use ($from, $to) {
                            $legacy->where('amount_paid', '>', 0)
                                ->whereBetween(DB::raw('COALESCE(paid_at, due_date)'), [$from, $to]);
                        });
                })
                ->get(['id', 'customer_id', 'amount_paid', 'paid_at', 'due_date']);

            $receivablesWithAnyPayment = DB::table('payments')
                ->where('tenant_id', $tenantId)
                ->where('payable_type', AccountReceivable::class)
                ->whereIn('payable_id', $receivables->pluck('id'))
                ->pluck('payable_id')
                ->flip();

            $customers = $receivables
                ->groupBy('customer_id')
                ->map(function ($group, $customerId) use ($paymentsByReceivable, $receivablesWithAnyPayment, $from, $to) {
                    $revenue = '0';
                    $transactions = 0;

                    foreach ($group as $receivable) {
                        $payments = $paymentsByReceivable->get($receivable->id, collect());

                        if ($payments->isNotEmpty()) {
                            $revenue = bcadd($revenue, Decimal::string($payments->sum('amount')), 2);
                            $transactions += $payments->count();
                            continue;
                        }

                        $paidReferenceDate = $receivable->paid_at ?? $receivable->due_date;

                        if ($receivablesWithAnyPayment->has($receivable->id) || ! $paidReferenceDate) {
                            continue;
                        }

                        if ($paidReferenceDate->lt($from) || $paidReferenceDate->gt($to)) {
                            continue;
                        }

                        $revenue = bcadd($revenue, Decimal::string($receivable->amount_paid), 2);
                        $transactions += 1;
                    }

                    return (object) [
                        'customer_id' => (int) $customerId,
                        'revenue' => $revenue,
                        'transactions' => $transactions,
                    ];
                })
                ->filter(fn ($customer) => bccomp(Decimal::string($customer->revenue), '0', 2) > 0)
                ->sortByDesc('revenue')
                ->values();

            $totalRevenue = Decimal::string($customers->sum('revenue'));
            $cumulative = '0';

            $customerMap = Customer::whereIn('id', $customers->pluck('customer_id')->unique())
                ->select('id', 'name', 'email')
                ->get()
                ->keyBy('id');

            $segmented = $customers->map(function ($c) use ($totalRevenue, &$cumulative, $customerMap) {
                $cumulative = bcadd($cumulative, Decimal::string($c->revenue), 2);
                $cumulativePercent = bccomp($totalRevenue, '0', 2) > 0
                    ? (float) bcmul(bcdiv($cumulative, $totalRevenue, 4), '100', 1)
                    : 0;

                $segment = match (true) {
                    $cumulativePercent <= 80 => 'A',
                    $cumulativePercent <= 95 => 'B',
                    default => 'C',
                };

                $customer = $customerMap->get($c->customer_id);

                return [
                    'customer_id' => $c->customer_id,
                    'name' => $customer instanceof Customer ? $customer->name : 'N/A',
                    'revenue' => round((float) $c->revenue, 2),
                    'transactions' => $c->transactions,
                    'revenue_percent' => $totalRevenue > 0 ? round(((float) $c->revenue / $totalRevenue) * 100, 1) : 0,
                    'cumulative_percent' => round($cumulativePercent, 1),
                    'segment' => $segment,
                ];
            });

            $summary = $segmented->groupBy('segment')->map(fn ($group) => [
                'count' => $group->count(),
                'revenue' => round($group->sum('revenue'), 2),
                'percent_customers' => $segmented->count() > 0 ? round(($group->count() / $segmented->count()) * 100, 1) : 0,
                'percent_revenue' => $totalRevenue > 0 ? round(($group->sum('revenue') / $totalRevenue) * 100, 1) : 0,
            ]);

            return ApiResponse::data([
                'data' => $segmented,
                'summary' => $summary,
                'total_revenue' => round((float) $totalRevenue, 2),
                'total_customers' => $segmented->count(),
            ]);
        } catch (\Exception $e) {
            Log::error('SalesAnalytics clientSegmentation failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao calcular segmentação.', 500);
        }
    }

    public function upsellSuggestions(Customer $customer): JsonResponse
    {
        $this->authorize('viewAny', AccountReceivable::class);

        try {
            $tenantId = $this->tenantId();

            // Cache schema checks to avoid repeated introspection on every request
            $schemaInfo = cache()->remember('upsell_schema_info', 3600, function () {
                $schema = DB::getSchemaBuilder();
                $serviceReferenceColumn = null;

                if ($schema->hasTable('work_order_items')) {
                    if ($schema->hasColumn('work_order_items', 'service_id')) {
                        $serviceReferenceColumn = 'service_id';
                    } elseif ($schema->hasColumn('work_order_items', 'reference_id')) {
                        $serviceReferenceColumn = 'reference_id';
                    }
                }

                return [
                    'has_tables' => $schema->hasTable('work_order_items') && $schema->hasTable('services'),
                    'has_type' => $schema->hasTable('work_order_items') && $schema->hasColumn('work_order_items', 'type'),
                    'has_unit_price' => $schema->hasTable('work_order_items') && $schema->hasColumn('work_order_items', 'unit_price'),
                    'service_reference_column' => $serviceReferenceColumn,
                ];
            });

            $serviceReferenceColumn = $schemaInfo['service_reference_column'];

            if (
                ! $schemaInfo['has_tables']
                || ! $schemaInfo['has_type']
                || ! $schemaInfo['has_unit_price']
                || $serviceReferenceColumn === null
            ) {
                return ApiResponse::data(['data' => collect()]);
            }

            $purchasedServiceIds = DB::table('work_order_items')
                ->join('work_orders', 'work_order_items.work_order_id', '=', 'work_orders.id')
                ->where('work_orders.customer_id', $customer->id)
                ->where('work_orders.tenant_id', $tenantId)
                ->where('work_order_items.type', 'service')
                ->pluck('work_order_items.'.$serviceReferenceColumn)
                ->unique();

            $suggestions = DB::table('work_order_items')
                ->join('work_orders', 'work_order_items.work_order_id', '=', 'work_orders.id')
                ->where('work_orders.tenant_id', $tenantId)
                ->where('work_order_items.type', 'service')
                ->whereNotIn('work_order_items.'.$serviceReferenceColumn, $purchasedServiceIds)
                ->select(
                    'work_order_items.'.$serviceReferenceColumn.' as service_id',
                    DB::raw('COUNT(DISTINCT work_orders.customer_id) as customer_count'),
                    DB::raw('COUNT(*) as usage_count'),
                    DB::raw('AVG(work_order_items.unit_price) as avg_price')
                )
                ->groupBy('work_order_items.'.$serviceReferenceColumn)
                ->orderByDesc('customer_count')
                ->limit(10)
                ->get()
                ->map(function ($s) use ($tenantId) {
                    $service = DB::table('services')->where('tenant_id', $tenantId)->where('id', $s->service_id)->first();
                    $serviceName = is_object($service) && property_exists($service, 'name')
                        ? (string) $service->name
                        : 'Serviço';

                    return [
                        'service_id' => $s->service_id,
                        'name' => $serviceName,
                        'avg_price' => round((float) $s->avg_price, 2),
                        'customer_count' => $s->customer_count,
                        'usage_count' => $s->usage_count,
                        'reason' => "{$s->customer_count} outros clientes já contrataram este serviço",
                    ];
                });

            return ApiResponse::data(['data' => $suggestions]);
        } catch (\Exception $e) {
            Log::error('SalesAnalytics upsellSuggestions failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao buscar sugestões de upsell.', 500);
        }
    }

    public function discountRequests(SalesAnalyticsDateRangeRequest $request): JsonResponse
    {
        $this->authorize('viewAny', AccountReceivable::class);

        try {
            $tenantId = $this->tenantId();

            $quotes = Quote::where('tenant_id', $tenantId)
                ->where('discount_amount', '>', 0)
                ->where('status', QuoteStatus::PENDING_INTERNAL_APPROVAL->value)
                ->with(['customer:id,name', 'seller:id,name'])
                ->orderByDesc('created_at')
                ->paginate(20);

            return ApiResponse::paginated($quotes);
        } catch (\Exception $e) {
            Log::error('SalesAnalytics discountRequests failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao listar solicitações de desconto.', 500);
        }
    }
}
