<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SystemImprovements\RecommendTechnicianRequest;
use App\Http\Requests\SystemImprovements\StoreCapaRecordRequest;
use App\Http\Requests\SystemImprovements\StoreCollectionRuleRequest;
use App\Http\Requests\SystemImprovements\StoreTechnicianSkillRequest;
use App\Http\Requests\SystemImprovements\UpdateCapaRecordRequest;
use App\Http\Requests\SystemImprovements\UpdateCollectionRuleRequest;
use App\Http\Requests\SystemImprovements\UpdateTechnicianSkillRequest;
use App\Models\AccountReceivable;
use App\Models\CapaRecord;
use App\Models\CollectionRule;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\Product;
use App\Models\TechnicianSkill;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\ApiResponse;
use App\Traits\ScopesByRole;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SystemImprovementsController extends Controller
{
    use ScopesByRole;

    private function tenantId(Request $request): int
    {
        $user = $request->user();

        return (int) ($user->current_tenant_id ?? $user->tenant_id);
    }

    // ═══════════════════════════════════════════════════════
    // TÉCNICOS: SKILL MATRIX
    // ═══════════════════════════════════════════════════════

    public function technicianSkills(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        $userId = $request->input('user_id');

        $skills = TechnicianSkill::where('tenant_id', $tenantId)
            ->with('user:id,name')
            ->when($userId, fn ($q, $id) => $q->where('user_id', $id))
            ->orderBy('user_id')
            ->orderBy('category')
            ->get();

        return ApiResponse::data($skills);
    }

    public function storeTechnicianSkill(StoreTechnicianSkillRequest $request): JsonResponse
    {
        $data = $request->validated();

        $skill = TechnicianSkill::create([
            ...$data,
            'tenant_id' => $this->tenantId($request),
        ]);

        return ApiResponse::data($skill, 201);
    }

    public function updateTechnicianSkill(UpdateTechnicianSkillRequest $request, TechnicianSkill $skill): JsonResponse
    {
        if ((int) $skill->tenant_id !== $this->tenantId($request)) {
            return ApiResponse::message('Habilidade não encontrada.', 404);
        }

        $skill->update($request->validated());

        return ApiResponse::data($skill);
    }

    public function destroyTechnicianSkill(TechnicianSkill $skill): JsonResponse
    {
        if ((int) $skill->tenant_id !== $this->tenantId(request())) {
            return ApiResponse::message('Habilidade não encontrada.', 404);
        }

        $skill->delete();

        return ApiResponse::message('Habilidade removida.');
    }

    public function skillMatrix(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);

        $technicians = User::where('tenant_id', $tenantId)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['tecnico', 'tecnico_vendedor']))
            ->with(['technicianSkills' => fn ($q) => $q->orderBy('category')])
            ->get()
            ->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'skills' => $user->technicianSkills,
                'skill_count' => $user->technicianSkills->count(),
                'avg_proficiency' => round($user->technicianSkills->avg('proficiency_level') ?? 0, 1),
                'expiring_certs' => $user->technicianSkills->filter(fn ($s) => $s->expires_at && $s->expires_at->lte(now()->addDays(60))
                )->count(),
            ]);

        return ApiResponse::data($technicians);
    }

    public function recommendTechnician(RecommendTechnicianRequest $request): JsonResponse
    {
        $data = $request->validated();
        $tenantId = $this->tenantId($request);

        $query = TechnicianSkill::where('tenant_id', $tenantId);

        if (! empty($data['equipment_type'])) {
            $query->where(function ($q) use ($data) {
                $q->where('skill_name', 'like', "%{$data['equipment_type']}%")
                    ->where('category', 'equipment_type');
            });
        }

        if (! empty($data['brand'])) {
            $query->orWhere(function ($q) use ($data, $tenantId) {
                $q->where('tenant_id', $tenantId)
                    ->where('skill_name', 'like', "%{$data['brand']}%")
                    ->where('category', 'brand');
            });
        }

        $recommendations = $query->with('user:id,name')
            ->orderByDesc('proficiency_level')
            ->get()
            ->groupBy('user_id')
            ->map(fn ($skills, $userId) => [
                'user' => $skills->first()->user,
                'matching_skills' => $skills->count(),
                'avg_proficiency' => round($skills->avg('proficiency_level'), 1),
                'skills' => $skills->pluck('skill_name'),
            ])
            ->sortByDesc('avg_proficiency')
            ->values();

        return ApiResponse::data($recommendations);
    }

    // ═══════════════════════════════════════════════════════
    // FINANCEIRO: RÉGUA DE COBRANÇA
    // ═══════════════════════════════════════════════════════

    public function collectionRules(Request $request): JsonResponse
    {
        $rules = CollectionRule::where('tenant_id', $this->tenantId($request))
            ->orderBy('days_offset')
            ->get();

        return ApiResponse::data($rules);
    }

    public function storeCollectionRule(StoreCollectionRuleRequest $request): JsonResponse
    {
        $data = $request->validated();

        $rule = CollectionRule::create([
            ...$data,
            'tenant_id' => $this->tenantId($request),
        ]);

        return ApiResponse::data($rule, 201);
    }

    public function updateCollectionRule(UpdateCollectionRuleRequest $request, CollectionRule $rule): JsonResponse
    {
        if ((int) $rule->tenant_id !== $this->tenantId($request)) {
            return ApiResponse::message('Regra não encontrada.', 404);
        }

        $rule->update($request->validated());

        return ApiResponse::data($rule);
    }

    public function destroyCollectionRule(CollectionRule $rule): JsonResponse
    {
        if ((int) $rule->tenant_id !== $this->tenantId(request())) {
            return ApiResponse::message('Regra não encontrada.', 404);
        }

        $rule->delete();

        return ApiResponse::message('Regra removida.');
    }

    public function agingReport(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);

        $aging = AccountReceivable::where('tenant_id', $tenantId)
            ->whereNotIn('status', ['paid', 'cancelled', 'renegotiated'])
            ->where('due_date', '<', now())
            ->select('id', 'tenant_id', 'customer_id', 'amount', 'amount_paid', 'due_date', 'status')
            ->get()
            ->groupBy(function ($ar) {
                $days = Carbon::parse($ar->due_date)->startOfDay()->diffInDays(now()->startOfDay());
                if ($days <= 30) {
                    return '0-30';
                }
                if ($days <= 60) {
                    return '31-60';
                }
                if ($days <= 90) {
                    return '61-90';
                }

                return '90+';
            })
            ->map(fn ($group, $range) => [
                'range' => $range,
                'count' => $group->count(),
                'total_value' => round($group->sum(fn ($item) => (float) $item->amount - (float) ($item->amount_paid ?? 0)), 2),
                'customers' => $group->groupBy('customer_id')->count(),
            ]);

        $totalOverdue = AccountReceivable::where('tenant_id', $tenantId)
            ->whereNotIn('status', ['paid', 'cancelled', 'renegotiated'])
            ->where('due_date', '<', now())
            ->sum(DB::raw('amount - amount_paid'));

        return ApiResponse::data([
            'aging' => $aging,
            'total_overdue' => $totalOverdue,
            'total_customers' => AccountReceivable::where('tenant_id', $tenantId)
                ->whereNotIn('status', ['paid', 'cancelled', 'renegotiated'])
                ->where('due_date', '<', now())
                ->distinct('customer_id')
                ->count('customer_id'),
        ]);
    }

    // ═══════════════════════════════════════════════════════
    // ESTOQUE: PREVISÃO DE DEMANDA
    // ═══════════════════════════════════════════════════════

    public function stockDemandForecast(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        $days = $request->input('days', 30);

        // Products used in upcoming work orders
        $upcomingDemand = DB::table('work_order_items')
            ->join('work_orders', 'work_order_items.work_order_id', '=', 'work_orders.id')
            ->where('work_orders.tenant_id', $tenantId)
            ->whereIn('work_orders.status', ['scheduled', 'in_progress', 'pending'])
            ->where('work_order_items.product_id', '!=', null)
            ->select(
                'work_order_items.product_id',
                DB::raw('SUM(work_order_items.quantity) as needed_quantity'),
                DB::raw('COUNT(DISTINCT work_orders.id) as os_count')
            )
            ->groupBy('work_order_items.product_id')
            ->get();

        // Pre-load all products and stock in bulk to avoid N+1
        $productIds = $upcomingDemand->pluck('product_id')->unique()->all();

        $productsMap = Product::whereIn('id', $productIds)
            ->select('id', 'name', 'code')
            ->get()
            ->keyBy('id');

        $stockMap = DB::table('warehouse_stocks')
            ->join('warehouses', 'warehouses.id', '=', 'warehouse_stocks.warehouse_id')
            ->where('warehouses.tenant_id', $tenantId)
            ->whereIn('warehouse_stocks.product_id', $productIds)
            ->select('warehouse_stocks.product_id', DB::raw('SUM(warehouse_stocks.quantity) as total_quantity'))
            ->groupBy('warehouse_stocks.product_id')
            ->pluck('total_quantity', 'product_id');

        $forecast = $upcomingDemand->map(function ($item) use ($productsMap, $stockMap) {
            $product = $productsMap->get($item->product_id);
            $currentStock = (float) ($stockMap->get($item->product_id) ?? 0);

            $deficit = max(0, $item->needed_quantity - $currentStock);

            return [
                'product_id' => $item->product_id,
                'product_name' => $product?->name ?? 'N/A',
                'product_code' => $product?->code ?? 'N/A',
                'needed_quantity' => $item->needed_quantity,
                'current_stock' => $currentStock,
                'deficit' => $deficit,
                'os_count' => $item->os_count,
                'status' => $deficit > 0 ? 'critical' : ($currentStock < $item->needed_quantity * 1.5 ? 'warning' : 'ok'),
            ];
        })->sortByDesc('deficit')->values();

        return ApiResponse::data($forecast);
    }

    // ═══════════════════════════════════════════════════════
    // QUALIDADE: CAPA (AÇÕES CORRETIVAS/PREVENTIVAS)
    // ═══════════════════════════════════════════════════════

    public function capaRecords(Request $request): JsonResponse
    {
        $records = CapaRecord::where('tenant_id', $this->tenantId($request))
            ->with(['assignee:id,name', 'creator:id,name'])
            ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->input('type'), fn ($q, $t) => $q->where('type', $t))
            ->when($request->input('source'), fn ($q, $s) => $q->where('source', $s))
            ->orderByDesc('created_at')
            ->paginate(min((int) $request->input('per_page', 20), 100));

        return ApiResponse::paginated($records);
    }

    public function storeCapaRecord(StoreCapaRecordRequest $request): JsonResponse
    {
        $data = $request->validated();

        $record = CapaRecord::create([
            ...$data,
            'tenant_id' => $this->tenantId($request),
            'created_by' => $request->user()->id,
        ]);

        return ApiResponse::data($record, 201);
    }

    public function updateCapaRecord(UpdateCapaRecordRequest $request, CapaRecord $record): JsonResponse
    {
        if ((int) $record->tenant_id !== $this->tenantId($request)) {
            return ApiResponse::message('Registro não encontrado.', 404);
        }

        $data = $request->validated();

        $record->update($data);

        if (($data['status'] ?? null) === 'closed') {
            $record->update(['closed_at' => now()]);
        }

        return ApiResponse::data($record);
    }

    public function qualityDashboard(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);

        $openCapas = CapaRecord::where('tenant_id', $tenantId)->open()->count();
        $overdueCapas = CapaRecord::where('tenant_id', $tenantId)->open()
            ->where('due_date', '<', now())->count();

        $reworkRate = 0;
        $totalOs = WorkOrder::where('tenant_id', $tenantId)
            ->where('created_at', '>=', now()->subMonths(3))->count();

        if ($totalOs > 0) {
            $reworks = WorkOrder::where('tenant_id', $tenantId)
                ->where('created_at', '>=', now()->subMonths(3))
                ->where(function ($q) {
                    $q->where('notes', 'like', '%retrabho%')
                        ->orWhere('notes', 'like', '%retrabalho%');
                })
                ->count();
            $reworkRate = round(($reworks / $totalOs) * 100, 1);
        }

        $complaintCount = DB::table('complaints')
            ->where('tenant_id', $tenantId)
            ->where('created_at', '>=', now()->subMonth())
            ->count();

        $avgResolutionDays = CapaRecord::where('tenant_id', $tenantId)
            ->whereNotNull('closed_at')
            ->where('closed_at', '>=', now()->subMonths(6))
            ->get()
            ->avg(fn ($r) => $r->created_at->diffInDays($r->closed_at));

        $npsScore = 0;
        $npsData = DB::table('nps_responses')
            ->where('tenant_id', $tenantId)
            ->where('created_at', '>=', now()->subMonths(3))
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN score >= 9 THEN 1 ELSE 0 END) as promoters,
                SUM(CASE WHEN score <= 6 THEN 1 ELSE 0 END) as detractors
            ')
            ->first();

        if ($npsData && $npsData->total > 0) {
            $npsScore = round((($npsData->promoters - $npsData->detractors) / $npsData->total) * 100);
        }

        return ApiResponse::data([
            'open_capas' => $openCapas,
            'overdue_capas' => $overdueCapas,
            'rework_rate' => $reworkRate,
            'complaints_month' => $complaintCount,
            'avg_resolution_days' => round($avgResolutionDays ?? 0, 1),
            'nps_score' => $npsScore,
        ]);
    }

    // ═══════════════════════════════════════════════════════
    // BUSCA GLOBAL
    // ═══════════════════════════════════════════════════════

    public function globalSearch(Request $request): JsonResponse
    {
        $query = $request->input('q');
        if (! $query || strlen($query) < 2) {
            return ApiResponse::data([]);
        }

        $tenantId = $this->tenantId($request);
        $limit = $request->input('limit', 20);

        $results = collect();

        // Search customers
        $customers = Customer::where('tenant_id', $tenantId)
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%")
                    ->orWhere('document', 'like', "%{$query}%")
                    ->orWhere('phone', 'like', "%{$query}%");
            })
            ->limit(5)
            ->select('id', 'name', 'email', 'phone')
            ->get()
            ->map(fn ($c) => [
                'type' => 'customer',
                'id' => $c->id,
                'title' => $c->name,
                'subtitle' => $c->email ?? $c->phone,
                'url' => "/cadastros/clientes/{$c->id}",
                'module' => 'Clientes',
            ]);
        $results = $results->merge($customers);

        // Search work orders
        $workOrders = WorkOrder::where('tenant_id', $tenantId)
            ->where(function ($q) use ($query) {
                $q->where('number', 'like', "%{$query}%")
                    ->orWhere('os_number', 'like', "%{$query}%")
                    ->orWhere('business_number', 'like', "%{$query}%");
            })
            ->limit(5)
            ->select('id', 'number', 'os_number', 'status')
            ->get()
            ->map(fn ($wo) => [
                'type' => 'work_order',
                'id' => $wo->id,
                'title' => "OS #{$wo->number}",
                'subtitle' => $wo->status,
                'url' => "/os/{$wo->id}",
                'module' => 'Ordens de Serviço',
            ]);
        $results = $results->merge($workOrders);

        // Search quotes
        $quotes = DB::table('quotes')
            ->where('tenant_id', $tenantId)
            ->where('quote_number', 'like', "%{$query}%")
            ->limit(5)
            ->get()
            ->map(fn ($q) => [
                'type' => 'quote',
                'id' => $q->id,
                'title' => "Orçamento #{$q->quote_number}",
                'subtitle' => $q->status,
                'url' => "/orcamentos/{$q->id}",
                'module' => 'Orçamentos',
            ]);
        $results = $results->merge($quotes);

        // Search equipments
        $equipments = Equipment::where('tenant_id', $tenantId)
            ->where(function ($q) use ($query) {
                $q->where('code', 'like', "%{$query}%")
                    ->orWhere('brand', 'like', "%{$query}%")
                    ->orWhere('model', 'like', "%{$query}%")
                    ->orWhere('serial_number', 'like', "%{$query}%");
            })
            ->limit(5)
            ->select('id', 'code', 'brand', 'model', 'serial_number')
            ->get()
            ->map(fn ($e) => [
                'type' => 'equipment',
                'id' => $e->id,
                'title' => "{$e->code} - {$e->brand} {$e->model}",
                'subtitle' => $e->serial_number,
                'url' => "/equipamentos/{$e->id}",
                'module' => 'Equipamentos',
            ]);
        $results = $results->merge($equipments);

        // Search deals
        $deals = DB::table('crm_deals')
            ->where('tenant_id', $tenantId)
            ->where('title', 'like', "%{$query}%")
            ->limit(5)
            ->get()
            ->map(fn ($d) => [
                'type' => 'deal',
                'id' => $d->id,
                'title' => $d->title,
                'subtitle' => 'R$ '.number_format($d->value, 2, ',', '.'),
                'url' => '/crm/pipeline',
                'module' => 'CRM',
            ]);
        $results = $results->merge($deals);

        return ApiResponse::data($results->take($limit)->values());
    }

    // ═══════════════════════════════════════════════════════
    // OS: ESTIMATIVA DE CUSTO EM TEMPO REAL
    // ═══════════════════════════════════════════════════════

    public function workOrderCostEstimate(Request $request, int $workOrderId): JsonResponse
    {
        $wo = WorkOrder::where('tenant_id', $this->tenantId($request))
            ->with(['items', 'timeEntries'])
            ->findOrFail($workOrderId);

        $partsTotal = '0';
        foreach ($wo->items as $i) {
            $lineTotal = bcmul((string) ($i->unit_price ?? 0), (string) ($i->quantity ?? 0), 2);
            $partsTotal = bcadd($partsTotal, $lineTotal, 2);
        }

        $laborMinutes = $wo->timeEntries->sum('duration_minutes');
        $laborHours = bcdiv((string) $laborMinutes, '60', 2);
        $avgHourlyRate = '120'; // default, could be from settings
        $laborCost = bcmul($laborHours, $avgHourlyRate, 2);

        $displacementCost = '50.00'; // default km cost

        $totalCost = bcadd(bcadd($partsTotal, $laborCost, 2), $displacementCost, 2);
        $revenue = (string) ($wo->total ?? 0);
        $margin = bccomp($revenue, '0', 2) > 0
            ? bcmul(bcdiv(bcsub($revenue, $totalCost, 2), $revenue, 4), '100', 1)
            : '0';

        return ApiResponse::data([
            'parts_cost' => $partsTotal,
            'labor_hours' => $laborHours,
            'labor_cost' => $laborCost,
            'displacement_cost' => $displacementCost,
            'total_cost' => $totalCost,
            'revenue' => $revenue,
            'margin_percent' => (float) $margin,
            'is_profitable' => bccomp($margin, '0', 1) > 0,
        ]);
    }
}
