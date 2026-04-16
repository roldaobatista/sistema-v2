<?php

namespace App\Actions\Crm;

use App\Http\Resources\CrmActivityResource;
use App\Models\AccountReceivable;
use App\Models\CrmActivity;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\EquipmentCalibration;
use App\Models\EquipmentDocument;
use App\Models\FiscalNote;
use App\Models\Quote;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\DB;

class GetCustomer360DataAction
{
    /**
     * @param  array<string, mixed>  $data
     * @return mixed
     */
    public function execute(array $data, Customer $customer, User $user, int $tenantId)
    {
        $customer->load([
            'contacts',
            'assignedSeller:id,name',
        ]);

        $tenantId = $customer->tenant_id;
        // Health score breakdown
        $healthBreakdown = $customer->health_score_breakdown;

        // Equipamentos
        $equipments = $customer->equipments()
            ->where('is_active', true)
            ->where('status', '!=', Equipment::STATUS_DISCARDED)
            ->get(['id', 'code', 'brand', 'model', 'category', 'status', 'next_calibration_at', 'last_calibration_at']);

        // Deals
        $dealsBaseQuery = $customer->deals();

        // Filtro por técnico (Regra de Negócio: Técnico só vê o que é dele)
        $isAdmin = $user->hasRole(Role::ADMIN) || $user->hasRole(Role::SUPER_ADMIN) || $user->hasPermissionTo('platform.dashboard.view');
        if (! $isAdmin) {
            $dealsBaseQuery->where('assigned_to', $user->id);
        }
        $deals = (clone $dealsBaseQuery)
            ->with(['stage:id,name,color', 'pipeline:id,name'])
            ->orderByDesc('updated_at')
            ->get();

        // Timeline (Atividades CRM)
        $timelineBaseQuery = CrmActivity::where('customer_id', $customer->id);

        if (! $isAdmin) {
            $timelineBaseQuery->where('user_id', $user->id);
        }
        $timeline = (clone $timelineBaseQuery)
            ->with(['user:id,name', 'contact:id,name'])
            ->orderByDesc('created_at')
            ->take(30)
            ->get();

        // Orçamentos
        $quotesBaseQuery = $customer->quotes();

        if (! $isAdmin) {
            $quotesBaseQuery->where('seller_id', $user->id);
        }
        $quotes = (clone $quotesBaseQuery)
            ->orderByDesc('created_at')
            ->take(10)
            ->get(['id', 'quote_number', 'status', 'total', 'created_at', 'approved_at']);

        // Ordens de Serviço
        $workOrdersBaseQuery = $customer->workOrders();

        if (! $isAdmin) {
            $workOrdersBaseQuery->where(function ($q) use ($user) {
                $q->where('assigned_to', $user->id)
                    ->orWhere('created_by', $user->id);
            });
        }
        $workOrders = (clone $workOrdersBaseQuery)
            ->orderByDesc('created_at')
            ->take(10)
            ->get(['id', 'number', 'os_number', 'status', 'total', 'created_at', 'completed_at']);

        // Financeiro - Receivables (todas as pendentes e recentes)
        $receivables = [];
        $pendingReceivablesSum = 0;

        if ($isAdmin || $user->hasPermissionTo('finance.receivable.view')) {
            $receivables = $customer->accountsReceivable()
                ->with(['workOrder:id,number'])
                ->orderByDesc('due_date')
                ->take(50)
                ->get();

            $pendingReceivablesSum = $customer->accountsReceivable()
                ->whereIn('status', [
                    AccountReceivable::STATUS_PENDING,
                    AccountReceivable::STATUS_PARTIAL,
                    AccountReceivable::STATUS_OVERDUE,
                ])
                ->sum(DB::raw('amount - amount_paid'));
        }

        // Notas Fiscais
        $fiscalNotes = [];
        if ($isAdmin || $user->hasPermissionTo('fiscal.note.view')) {
            $fiscalNotes = FiscalNote::where('customer_id', $customer->id)
                ->orderByDesc('created_at')
                ->take(20)
                ->get();
        }

        // Chamados
        $serviceCallsBaseQuery = $customer->serviceCalls();

        if (! $isAdmin) {
            $serviceCallsBaseQuery->where('created_by', $user->id);
        }
        $serviceCalls = (clone $serviceCallsBaseQuery)
            ->orderByDesc('created_at')
            ->take(20)
            ->get();

        // Documentos (certificados e documentos dos equipamentos do cliente)
        $documents = collect();
        if ($isAdmin || $user->hasPermissionTo('customer.document.view')) {
            $equipmentIds = $customer->equipments()->pluck('id');
            $documents = EquipmentDocument::whereIn('equipment_id', $equipmentIds)
                ->with(['equipment:id,code,brand,model'])
                ->orderByDesc('created_at')
                ->get();
        }

        // ─── Fase 2: Métricas de Inteligência ────────────────

        // 1. Health Metrics (Churn)
        $lastActivity = (clone $timelineBaseQuery)->latest()->first();
        $lastContactDays = $lastActivity ? now()->diffInDays($lastActivity->created_at) : 999;
        $churnRisk = $lastContactDays > 150 ? 'crítico' : ($lastContactDays > 90 ? 'alto' : ($lastContactDays > 45 ? 'médio' : 'baixo'));

        // 2. Commercial Metrics (LTV & Conversão)
        $wonQuotesSum = (clone $quotesBaseQuery)
            ->whereIn('status', [Quote::STATUS_APPROVED, Quote::STATUS_INVOICED])
            ->sum('total');
        $paidOsSum = (clone $workOrdersBaseQuery)
            ->whereIn('status', [WorkOrder::STATUS_COMPLETED, WorkOrder::STATUS_INVOICED])
            ->sum('total');
        $ltv = bcadd((string) $wonQuotesSum, (string) $paidOsSum, 2);

        $totalQuotesCount = (clone $quotesBaseQuery)->count();
        $approvedQuotesCount = (clone $quotesBaseQuery)
            ->whereIn('status', [Quote::STATUS_APPROVED, Quote::STATUS_INVOICED])
            ->count();
        $conversionRate = $totalQuotesCount > 0 ? round(($approvedQuotesCount / $totalQuotesCount) * 100, 1) : 0;

        // 3. Forecast de Calibrações (Próximos 6 meses)
        $forecast = [];
        for ($i = 0; $i < 6; $i++) {
            $monthDate = now()->addMonths($i);
            $count = $customer->equipments()
                ->where('next_calibration_at', '>=', $monthDate->startOfMonth()->toDateString())
                ->where('next_calibration_at', '<=', $monthDate->endOfMonth()->toDateString())
                ->count();

            $forecast[] = [
                'name' => $monthDate->translatedFormat('M/y'),
                'count' => $count,
            ];
        }

        // 4. Trend Data (Tendência do Equipamento Principal)
        $mainEquipment = $customer->equipments()->withCount('calibrations')->orderByDesc('calibrations_count')->first();
        $trendData = [];
        /** @var Equipment|null $mainEquipment */
        if ($mainEquipment) {
            $trendData = EquipmentCalibration::where('equipment_id', $mainEquipment->id)
                ->orderBy('calibration_date')
                ->take(10)
                ->get()
                ->map(function (EquipmentCalibration $calibration): array {
                    return [
                        'date' => $calibration->calibration_date instanceof \DateTimeInterface
                            ? $calibration->calibration_date->format('d/m/y')
                            : 'N/A',
                        'error' => (float) ($calibration->error_found ?? 0),
                        'uncertainty' => (float) ($calibration->uncertainty ?? 0),
                    ];
                });
        }

        // 5. Radar de Saúde (Holístico)
        $financeScore = $pendingReceivablesSum > 0 ? 50 : 100;
        $engagementScore = max(0, 100 - ($lastContactDays / 1.5));
        $equipmentTotal = $customer->equipments()->count();
        // calibration_status is a PHP accessor, not a DB column.
        // Count equipments whose next_calibration_at is in the future (i.e. "em_dia")
        $emDiaCount = $equipmentTotal > 0
            ? $customer->equipments()
                ->where(function ($q) {
                    $q->whereNotNull('next_calibration_at')
                        ->where('next_calibration_at', '>', now());
                })
                ->count()
            : 0;
        $metrologyScore = $equipmentTotal > 0
            ? round($emDiaCount / $equipmentTotal * 100, 1)
            : 100;

        $radarData = [
            ['subject' => 'Financeiro', 'value' => $financeScore],
            ['subject' => 'Comercial', 'value' => $conversionRate],
            ['subject' => 'Engajamento', 'value' => $engagementScore],
            ['subject' => 'Metrologia', 'value' => $metrologyScore],
            ['subject' => 'Lealdade', 'value' => $customer->is_active ? 100 : 0],
        ];

        // 6. Benchmarking (Segmento)
        $segmentAvgRevenue = Customer::where('tenant_id', $tenantId)
            ->where('segment', $customer->segment)
            ->where('id', '!=', $customer->id)
            ->withSum('workOrders', 'total')
            ->get()
            ->avg('work_orders_sum_total') ?? 0;

        // 7. Automação de Retenção (CRM Proativo)
        if ($customer->id && in_array($churnRisk, ['crítico', 'alto'])) {
            try {
                $hasOpenFollowUp = CrmActivity::where('customer_id', $customer->id)
                    ->where('type', 'call')
                    ->where('is_automated', true)
                    ->whereNull('completed_at')
                    ->where('title', 'LIKE', '%Retenção%')
                    ->exists();

                if (! $hasOpenFollowUp) {
                    CrmActivity::create([
                        'tenant_id' => $tenantId,
                        'customer_id' => $customer->id,
                        'user_id' => $customer->assigned_seller_id ?? $user->id,
                        'title' => 'Retenção: Cliente com Risco de Churn '.ucfirst($churnRisk),
                        'description' => "Automação: O sistema detectou risco de perda devido à inatividade de {$lastContactDays} dias. Favor entrar em contato.",
                        'type' => 'call',
                        'is_automated' => true,
                        'scheduled_at' => now()->addDays(2),
                    ]);
                }
            } catch (\Exception $e) {
                report($e);
            }
        }

        return ApiResponse::data([
            'customer' => $customer,
            'health_breakdown' => $healthBreakdown,
            'equipments' => $equipments,
            'deals' => $deals,
            'timeline' => CrmActivityResource::collection($timeline)->resolve(),
            'work_orders' => $workOrders,
            'service_calls' => $serviceCalls,
            'quotes' => $quotes,
            'receivables' => $receivables,
            'pending_receivables' => (string) $pendingReceivablesSum,
            'fiscal_notes' => $fiscalNotes,
            'documents' => $documents->values(),
            'metrics' => [
                'churn_risk' => $churnRisk,
                'last_contact_days' => $lastContactDays,
                'ltv' => $ltv,
                'conversion_rate' => $conversionRate,
                'forecast' => $forecast,
                'trend' => $trendData,
                'radar' => $radarData,
                'benchmarking' => [
                    ['name' => 'Este Cliente', 'value' => (string) $paidOsSum],
                    ['name' => 'Média do Segmento', 'value' => (string) $segmentAvgRevenue],
                ],
                'main_equipment_name' => $mainEquipment ? ($mainEquipment->brand.' '.$mainEquipment->model) : null,
            ],
        ]);
    }
}
