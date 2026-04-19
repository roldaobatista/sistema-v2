<?php

namespace App\Services;

use App\Enums\ServiceCallStatus;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\Expense;
use App\Models\ServiceCall;
use App\Models\WorkOrder;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AIAnalyticsService
{
    /**
     * 1. Manutenção Preditiva — Análise do histórico de calibrações
     */
    public function predictiveMaintenance(int $tenantId, array $filters = []): array
    {
        try {
            $equipments = Equipment::where('tenant_id', $tenantId)
                ->with(['workOrders' => fn ($q) => $q->orderBy('created_at', 'desc')->limit(10)])
                ->get();

            $predictions = [];
            foreach ($equipments as $equip) {
                $calibrations = $equip->workOrders;
                if ($calibrations->count() < 2) {
                    continue;
                }

                $intervals = [];
                $dates = $calibrations->pluck('created_at')->sort()->values();
                for ($i = 1; $i < $dates->count(); $i++) {
                    $intervals[] = $dates[$i - 1]->diffInDays($dates[$i]);
                }

                if (empty($intervals)) {
                    continue;
                }

                $avgInterval = array_sum($intervals) / count($intervals);
                $lastDate = $dates->last();
                $predictedNext = Carbon::parse($lastDate)->addDays((int) $avgInterval);
                $daysUntil = now()->diffInDays($predictedNext, false);

                $risk = 'low';
                if ($daysUntil < 0) {
                    $risk = 'critical';
                } elseif ($daysUntil < 15) {
                    $risk = 'high';
                } elseif ($daysUntil < 30) {
                    $risk = 'medium';
                }

                $predictions[] = [
                    'equipment_id' => $equip->id,
                    'equipment_name' => $equip->name ?? $equip->description ?? "Equip #{$equip->id}",
                    'serial_number' => $equip->serial_number ?? null,
                    'total_calibrations' => $calibrations->count(),
                    'avg_interval_days' => round($avgInterval, 1),
                    'last_calibration' => $lastDate->toDateString(),
                    'predicted_next' => $predictedNext->toDateString(),
                    'days_until_next' => $daysUntil,
                    'risk_level' => $risk,
                ];
            }

            usort($predictions, fn ($a, $b) => $a['days_until_next'] <=> $b['days_until_next']);

            return [
                'predictions' => array_slice($predictions, 0, $filters['limit'] ?? 50),
                'total_analyzed' => $equipments->count(),
                'critical_count' => count(array_filter($predictions, fn ($p) => $p['risk_level'] === 'critical')),
                'high_count' => count(array_filter($predictions, fn ($p) => $p['risk_level'] === 'high')),
            ];
        } catch (\Exception $e) {
            Log::error('AI predictiveMaintenance failed', ['error' => $e->getMessage()]);

            return ['predictions' => [], 'total_analyzed' => 0, 'critical_count' => 0, 'high_count' => 0];
        }
    }

    /**
     * 2. OCR/Digitalização de Recibos — Análise de padrões em despesas
     */
    public function expenseOcrAnalysis(int $tenantId, array $filters = []): array
    {
        try {
            $expenses = Expense::where('tenant_id', $tenantId)
                ->where('created_at', '>=', now()->subMonths(6))
                ->get();

            // Detectar duplicatas potenciais (mesmo valor + mesma data + mesmo usuário)
            $grouped = $expenses->groupBy(fn ($e) => $e->created_by.'_'.$e->amount.'_'.Carbon::parse($e->date ?? $e->created_at)->toDateString());
            $duplicates = [];
            foreach ($grouped as $key => $group) {
                if ($group->count() > 1) {
                    $duplicates[] = [
                        'count' => $group->count(),
                        'amount' => $group->first()->amount,
                        'date' => Carbon::parse($group->first()->date ?? $group->first()->created_at)->toDateString(),
                        'expense_ids' => $group->pluck('id')->toArray(),
                    ];
                }
            }

            // Categorização por frequência
            $categoryStats = $expenses->groupBy('category_id')->map(fn ($group) => [
                'count' => $group->count(),
                'total' => round($group->sum('amount'), 2),
                'avg' => round($group->avg('amount'), 2),
            ])->sortByDesc('total')->values();

            // Despesas sem comprovante
            $withoutReceipt = $expenses->filter(fn ($e) => empty($e->receipt_path) && empty($e->attachment))->count();

            return [
                'total_expenses' => $expenses->count(),
                'potential_duplicates' => $duplicates,
                'duplicate_count' => count($duplicates),
                'category_distribution' => $categoryStats->take(10),
                'without_receipt_count' => $withoutReceipt,
                'without_receipt_pct' => $expenses->count() > 0 ? round(($withoutReceipt / $expenses->count()) * 100, 1) : 0,
                'total_amount' => round($expenses->sum('amount'), 2),
                'avg_expense' => round($expenses->avg('amount'), 2),
            ];
        } catch (\Exception $e) {
            Log::error('AI expenseOcrAnalysis failed', ['error' => $e->getMessage()]);

            return ['total_expenses' => 0, 'potential_duplicates' => [], 'duplicate_count' => 0, 'category_distribution' => [], 'without_receipt_count' => 0, 'without_receipt_pct' => 0, 'total_amount' => 0, 'avg_expense' => 0];
        }
    }

    /**
     * 3. Chatbot de Triagem — Sugestões de prioridade/técnico
     */
    public function triageSuggestions(int $tenantId, array $filters = []): array
    {
        try {
            $recentCalls = ServiceCall::where('tenant_id', $tenantId)
                ->where('created_at', '>=', now()->subMonths(3))
                ->with('technician:id,name')
                ->get();

            $typePatterns = collect($this->serviceCallTopicKeywords())
                ->map(function (array $keywords, string $topic) use ($recentCalls) {
                    $matching = $recentCalls->filter(function (ServiceCall $call) use ($keywords) {
                        $text = strtolower($this->serviceCallText($call));

                        foreach ($keywords as $keyword) {
                            if (str_contains($text, $keyword)) {
                                return true;
                            }
                        }

                        return false;
                    });

                    return [
                        'count' => $matching->count(),
                        'avg_resolution_hours' => round($matching->avg(fn ($c) => $c->completed_at && $c->created_at
                            ? Carbon::parse($c->created_at)->diffInHours(Carbon::parse($c->completed_at))
                            : null
                        ), 1),
                    ];
                })
                ->filter(fn (array $stats) => $stats['count'] > 0)
                ->sortByDesc('count');

            $techFrequency = $recentCalls
                ->filter(fn (ServiceCall $call) => ! empty($call->technician_id))
                ->groupBy('technician_id')
                ->map(fn ($group) => [
                    'count' => $group->count(),
                    'name' => $group->first()?->technician?->name ?? "Tecnico #{$group->first()->technician_id}",
                ])
                ->sortByDesc('count')
                ->take(10);

            $hourDistribution = $recentCalls->groupBy(fn ($c) => Carbon::parse($c->created_at)->format('H'))
                ->map(fn ($g) => $g->count())
                ->sortKeys();

            return [
                'total_calls_analyzed' => $recentCalls->count(),
                'type_patterns' => $typePatterns->toArray(),
                'technician_frequency' => $techFrequency->toArray(),
                'peak_hours' => $hourDistribution->toArray(),
                'suggestions' => [
                    'high_priority_keywords' => ['urgente', 'parada', 'interditada', 'inmetro', 'prazo'],
                    'auto_assign_rules' => 'Baseado no historico, tecnicos mais recorrentes por assunto podem ser sugeridos para novos chamados.',
                ],
            ];
        } catch (\Exception $e) {
            Log::error('AI triageSuggestions failed', ['error' => $e->getMessage()]);

            return ['total_calls_analyzed' => 0, 'type_patterns' => [], 'technician_frequency' => [], 'peak_hours' => [], 'suggestions' => []];
        }
    }

    /**
     * 4. Análise de Sentimento — Score de satisfação
     */
    public function sentimentAnalysis(int $tenantId, array $filters = []): array
    {
        try {
            $workOrders = WorkOrder::where('tenant_id', $tenantId)
                ->where('created_at', '>=', now()->subMonths(6))
                ->get();

            // NPS simulado baseado em avaliações de OS
            $rated = DB::table('work_order_ratings')
                ->whereIn('work_order_id', $workOrders->pluck('id'))
                ->get();

            $npsScores = $rated->pluck('rating');
            $promoters = $npsScores->filter(fn ($s) => $s >= 9)->count();
            $detractors = $npsScores->filter(fn ($s) => $s <= 6)->count();
            $total = $npsScores->count();
            $nps = $total > 0 ? round((($promoters - $detractors) / $total) * 100, 1) : null;

            // Tempo médio de resolução como proxy de satisfação
            $completed = $workOrders->filter(fn ($wo) => $wo->status === 'completed' && $wo->completed_at);
            $avgResolution = $completed->count() > 0
                ? round($completed->avg(fn ($wo) => Carbon::parse($wo->created_at)->diffInHours(Carbon::parse($wo->completed_at))), 1)
                : null;

            // Tendência mensal
            $monthlyTrend = $rated->groupBy(fn ($r) => Carbon::parse($r->created_at)->format('Y-m'))
                ->map(fn ($g) => [
                    'avg_rating' => round($g->avg('rating'), 2),
                    'count' => $g->count(),
                ])
                ->sortKeys()
                ->toArray();

            return [
                'nps_score' => $nps,
                'total_ratings' => $total,
                'promoters' => $promoters,
                'neutrals' => $total - $promoters - $detractors,
                'detractors' => $detractors,
                'avg_resolution_hours' => $avgResolution,
                'total_completed' => $completed->count(),
                'monthly_trend' => $monthlyTrend,
                'sentiment_label' => $nps !== null ? ($nps >= 50 ? 'Excelente' : ($nps >= 0 ? 'Bom' : 'Crítico')) : 'Sem dados',
            ];
        } catch (\Exception $e) {
            Log::error('AI sentimentAnalysis failed', ['error' => $e->getMessage()]);

            return ['nps_score' => null, 'total_ratings' => 0, 'promoters' => 0, 'neutrals' => 0, 'detractors' => 0, 'avg_resolution_hours' => null, 'total_completed' => 0, 'monthly_trend' => [], 'sentiment_label' => 'Erro'];
        }
    }

    /**
     * 5. Sugestões de Preços Dinâmicos
     */
    public function dynamicPricing(int $tenantId, array $filters = []): array
    {
        try {
            // Historico de itens de OS com preços
            $items = DB::table('work_order_items as woi')
                ->join('work_orders as wo', 'wo.id', '=', 'woi.work_order_id')
                ->where('wo.tenant_id', $tenantId)
                ->where('wo.created_at', '>=', now()->subYear())
                ->select('woi.product_id', 'woi.service_id', 'woi.unit_price', 'woi.quantity', 'wo.created_at', 'wo.customer_id')
                ->get();

            $byProduct = $items->filter(fn ($i) => $i->product_id)->groupBy('product_id');
            $byService = $items->filter(fn ($i) => $i->service_id)->groupBy('service_id');

            $suggestions = [];
            foreach ($byService as $serviceId => $serviceItems) {
                if ($serviceItems->count() < 3) {
                    continue;
                }

                $prices = $serviceItems->pluck('unit_price');
                $avg = round($prices->avg(), 2);
                $min = round($prices->min(), 2);
                $max = round($prices->max(), 2);
                $stdDev = $this->standardDeviation($prices->toArray());

                // Sugestão: média + 10% margem
                $suggested = round($avg * 1.10, 2);

                $suggestions[] = [
                    'type' => 'service',
                    'item_id' => $serviceId,
                    'transactions' => $serviceItems->count(),
                    'current_avg' => $avg,
                    'current_min' => $min,
                    'current_max' => $max,
                    'std_deviation' => round($stdDev, 2),
                    'suggested_price' => $suggested,
                    'confidence' => $serviceItems->count() >= 10 ? 'alta' : ($serviceItems->count() >= 5 ? 'média' : 'baixa'),
                ];
            }

            usort($suggestions, fn ($a, $b) => $b['transactions'] <=> $a['transactions']);

            return [
                'suggestions' => array_slice($suggestions, 0, 20),
                'total_items_analyzed' => $items->count(),
                'products_analyzed' => $byProduct->count(),
                'services_analyzed' => $byService->count(),
            ];
        } catch (\Exception $e) {
            Log::error('AI dynamicPricing failed', ['error' => $e->getMessage()]);

            return ['suggestions' => [], 'total_items_analyzed' => 0, 'products_analyzed' => 0, 'services_analyzed' => 0];
        }
    }

    /**
     * 6. Detecção de Anomalias Financeiras — Z-score + IQR
     */
    public function financialAnomalies(int $tenantId, array $filters = []): array
    {
        try {
            $expenses = Expense::where('tenant_id', $tenantId)
                ->where('created_at', '>=', now()->subMonths(6))
                ->whereNotNull('amount')
                ->where('amount', '>', 0)
                ->get();

            $amounts = $expenses->pluck('amount')->sort()->values()->toArray();
            if (count($amounts) < 5) {
                return ['anomalies' => [], 'total_analyzed' => count($amounts), 'stats' => []];
            }

            $mean = array_sum($amounts) / count($amounts);
            $stdDev = $this->standardDeviation($amounts);

            // IQR
            $q1Index = (int) floor(count($amounts) * 0.25);
            $q3Index = (int) floor(count($amounts) * 0.75);
            $q1 = $amounts[$q1Index];
            $q3 = $amounts[$q3Index];
            $iqr = $q3 - $q1;
            $lowerBound = $q1 - (1.5 * $iqr);
            $upperBound = $q3 + (1.5 * $iqr);

            $anomalies = [];
            foreach ($expenses as $exp) {
                $zScore = $stdDev > 0 ? ($exp->amount - $mean) / $stdDev : 0;
                $isOutlier = abs($zScore) > 2 || $exp->amount < $lowerBound || $exp->amount > $upperBound;

                if ($isOutlier) {
                    $anomalies[] = [
                        'expense_id' => $exp->id,
                        'amount' => round($exp->amount, 2),
                        'date' => Carbon::parse($exp->date ?? $exp->created_at)->toDateString(),
                        'description' => $exp->description ?? '',
                        'z_score' => round($zScore, 2),
                        'anomaly_type' => $zScore > 2 ? 'muito_alta' : ($zScore < -2 ? 'muito_baixa' : 'fora_iqr'),
                        'severity' => abs($zScore) > 3 ? 'critical' : 'warning',
                    ];
                }
            }

            usort($anomalies, fn ($a, $b) => abs($b['z_score']) <=> abs($a['z_score']));

            return [
                'anomalies' => array_slice($anomalies, 0, 30),
                'total_analyzed' => $expenses->count(),
                'anomaly_count' => count($anomalies),
                'stats' => [
                    'mean' => round($mean, 2),
                    'std_deviation' => round($stdDev, 2),
                    'q1' => round($q1, 2),
                    'q3' => round($q3, 2),
                    'iqr' => round($iqr, 2),
                    'lower_bound' => round($lowerBound, 2),
                    'upper_bound' => round($upperBound, 2),
                ],
            ];
        } catch (\Exception $e) {
            Log::error('AI financialAnomalies failed', ['error' => $e->getMessage()]);

            return ['anomalies' => [], 'total_analyzed' => 0, 'anomaly_count' => 0, 'stats' => []];
        }
    }

    /**
     * 7. Assistente de Voz — Ações rápidas contextuais
     */
    public function voiceCommandSuggestions(int $tenantId, array $filters = []): array
    {
        try {
            $pendingOs = WorkOrder::where('tenant_id', $tenantId)
                ->whereIn('status', ['pending', 'in_progress', 'scheduled'])
                ->count();

            $openCalls = ServiceCall::where('tenant_id', $tenantId)
                ->whereIn('status', ServiceCallStatus::activeStatuses())
                ->count();

            $pendingExpenses = Expense::where('tenant_id', $tenantId)
                ->where('status', 'pending')
                ->count();

            return [
                'context' => [
                    'pending_work_orders' => $pendingOs,
                    'open_service_calls' => $openCalls,
                    'pending_expenses' => $pendingExpenses,
                ],
                'suggested_commands' => [
                    ['command' => 'listar minhas OS do dia', 'action' => 'GET /work-orders?status=scheduled&date=today', 'priority' => 1],
                    ['command' => 'iniciar OS #{id}', 'action' => 'POST /work-orders/{id}/status', 'priority' => 2],
                    ['command' => 'registrar despesa', 'action' => 'POST /expenses', 'priority' => 3],
                    ['command' => 'ver agenda de hoje', 'action' => 'GET /schedules?date=today', 'priority' => 4],
                    ['command' => 'concluir OS atual', 'action' => 'POST /work-orders/{id}/status', 'priority' => 5],
                    ['command' => 'tirar foto do equipamento', 'action' => 'POST /work-orders/{id}/attachments', 'priority' => 6],
                    ['command' => 'preencher checklist', 'action' => 'POST /checklist-submissions', 'priority' => 7],
                    ['command' => 'calcular rota', 'action' => 'POST /operational/route-optimization', 'priority' => 8],
                ],
            ];
        } catch (\Exception $e) {
            Log::error('AI voiceCommandSuggestions failed', ['error' => $e->getMessage()]);

            return ['context' => [], 'suggested_commands' => []];
        }
    }

    /**
     * 8. Relatórios em Linguagem Natural
     */
    public function naturalLanguageReport(int $tenantId, array $filters = []): array
    {
        try {
            $period = $filters['period'] ?? 'month';
            $startDate = match ($period) {
                'week' => now()->startOfWeek(),
                'month' => now()->startOfMonth(),
                'quarter' => now()->startOfQuarter(),
                'year' => now()->startOfYear(),
                default => now()->startOfMonth(),
            };

            $osCount = WorkOrder::where('tenant_id', $tenantId)->where('created_at', '>=', $startDate)->count();
            $osCompleted = WorkOrder::where('tenant_id', $tenantId)->where('created_at', '>=', $startDate)->where('status', 'completed')->count();

            $revenue = DB::table('accounts_receivable')
                ->where('tenant_id', $tenantId)
                ->where('created_at', '>=', $startDate)
                ->sum('amount');

            $expenseTotal = Expense::where('tenant_id', $tenantId)
                ->where('created_at', '>=', $startDate)
                ->sum('amount');

            $newCustomers = Customer::where('tenant_id', $tenantId)->where('created_at', '>=', $startDate)->count();

            $periodLabel = match ($period) {
                'week' => 'esta semana',
                'month' => 'este mês',
                'quarter' => 'este trimestre',
                'year' => 'este ano',
                default => 'este mês',
            };

            $completionRate = $osCount > 0 ? round(($osCompleted / $osCount) * 100, 1) : 0;
            $profit = $revenue - $expenseTotal;

            $report = "📊 **Resumo {$periodLabel}**\n\n";
            $report .= "Foram registradas **{$osCount} ordens de serviço**, das quais **{$osCompleted}** foram concluídas ({$completionRate}% de taxa de conclusão).\n\n";
            $report .= 'O faturamento total foi de **R$ '.number_format($revenue, 2, ',', '.').'**, ';
            $report .= 'com despesas de **R$ '.number_format($expenseTotal, 2, ',', '.').'**, ';
            $report .= 'resultando em um lucro bruto de **R$ '.number_format($profit, 2, ',', '.')."**.\n\n";
            $report .= "Foram cadastrados **{$newCustomers} novos clientes** no período.\n\n";

            if ($completionRate >= 90) {
                $report .= "✅ A taxa de conclusão está excelente! Parabéns à equipe.\n";
            } elseif ($completionRate >= 70) {
                $report .= "⚠️ A taxa de conclusão está boa, mas há espaço para melhoria.\n";
            } else {
                $report .= "🔴 A taxa de conclusão está abaixo do esperado. Verificar gargalos.\n";
            }

            return [
                'report_text' => $report,
                'period' => $period,
                'metrics' => [
                    'os_total' => $osCount,
                    'os_completed' => $osCompleted,
                    'completion_rate' => $completionRate,
                    'revenue' => round($revenue, 2),
                    'expenses' => round($expenseTotal, 2),
                    'profit' => round($profit, 2),
                    'new_customers' => $newCustomers,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('AI naturalLanguageReport failed', ['error' => $e->getMessage()]);

            return ['report_text' => 'Erro ao gerar relatório.', 'period' => $filters['period'] ?? 'month', 'metrics' => []];
        }
    }

    /**
     * 9. Clusterização de Clientes — RFM (Recência, Frequência, Monetário)
     */
    public function customerClustering(int $tenantId, array $filters = []): array
    {
        try {
            $customers = Customer::where('tenant_id', $tenantId)
                ->withCount(['workOrders' => fn ($q) => $q->where('created_at', '>=', now()->subYear())])
                ->get();

            $clusters = [];
            foreach ($customers as $customer) {
                $lastOs = WorkOrder::where('customer_id', $customer->id)
                    ->where('tenant_id', $tenantId)
                    ->orderBy('created_at', 'desc')
                    ->first();

                $recency = $lastOs ? now()->diffInDays(Carbon::parse($lastOs->created_at)) : 999;
                $frequency = $customer->work_orders_count;

                $monetary = DB::table('accounts_receivable')
                    ->where('customer_id', $customer->id)
                    ->where('tenant_id', $tenantId)
                    ->where('created_at', '>=', now()->subYear())
                    ->sum('amount');

                // Classificação RFM simplificada
                $rScore = $recency <= 30 ? 5 : ($recency <= 90 ? 4 : ($recency <= 180 ? 3 : ($recency <= 365 ? 2 : 1)));
                $fScore = $frequency >= 10 ? 5 : ($frequency >= 5 ? 4 : ($frequency >= 3 ? 3 : ($frequency >= 1 ? 2 : 1)));
                $mScore = $monetary >= 50000 ? 5 : ($monetary >= 20000 ? 4 : ($monetary >= 5000 ? 3 : ($monetary >= 1000 ? 2 : 1)));

                $totalScore = $rScore + $fScore + $mScore;
                $segment = match (true) {
                    $totalScore >= 13 => 'Champions',
                    $totalScore >= 10 => 'Loyal Customers',
                    $totalScore >= 7 => 'Potential Loyalists',
                    $totalScore >= 5 => 'At Risk',
                    default => 'Hibernating',
                };

                $clusters[] = [
                    'customer_id' => $customer->id,
                    'customer_name' => $customer->name ?? $customer->company_name ?? "Cliente #{$customer->id}",
                    'recency_days' => $recency,
                    'frequency' => $frequency,
                    'monetary' => round($monetary, 2),
                    'r_score' => $rScore,
                    'f_score' => $fScore,
                    'm_score' => $mScore,
                    'total_score' => $totalScore,
                    'segment' => $segment,
                ];
            }

            // Agrupar por segmento
            $segmentSummary = collect($clusters)->groupBy('segment')->map(fn ($g) => [
                'count' => $g->count(),
                'avg_monetary' => round($g->avg('monetary'), 2),
                'avg_frequency' => round($g->avg('frequency'), 1),
            ])->toArray();

            usort($clusters, fn ($a, $b) => $b['total_score'] <=> $a['total_score']);

            return [
                'clusters' => array_slice($clusters, 0, $filters['limit'] ?? 50),
                'total_customers' => count($clusters),
                'segment_summary' => $segmentSummary,
            ];
        } catch (\Exception $e) {
            Log::error('AI customerClustering failed', ['error' => $e->getMessage()]);

            return ['clusters' => [], 'total_customers' => 0, 'segment_summary' => []];
        }
    }

    /**
     * 10. Reconhecimento de Imagem de Equipamentos — Análise de completude
     */
    public function equipmentImageAnalysis(int $tenantId, array $filters = []): array
    {
        try {
            $equipments = Equipment::where('tenant_id', $tenantId)->get();

            $withPhotos = 0;
            $withoutPhotos = 0;
            $analysis = [];

            foreach ($equipments as $equip) {
                $attachments = DB::table('work_order_attachments as woa')
                    ->join('work_orders as wo', 'wo.id', '=', 'woa.work_order_id')
                    ->join('work_order_equipment as woe', 'woe.work_order_id', '=', 'wo.id')
                    ->where('woe.equipment_id', $equip->id)
                    ->count();

                if ($attachments > 0) {
                    $withPhotos++;
                } else {
                    $withoutPhotos++;
                }

                if ($attachments === 0) {
                    $analysis[] = [
                        'equipment_id' => $equip->id,
                        'equipment_name' => $equip->name ?? $equip->description ?? "Equip #{$equip->id}",
                        'serial_number' => $equip->serial_number ?? null,
                        'photo_count' => 0,
                        'status' => 'missing_photos',
                        'recommendation' => 'Solicitar fotos na próxima visita técnica',
                    ];
                }
            }

            return [
                'total_equipments' => $equipments->count(),
                'with_photos' => $withPhotos,
                'without_photos' => $withoutPhotos,
                'coverage_pct' => $equipments->count() > 0 ? round(($withPhotos / $equipments->count()) * 100, 1) : 0,
                'missing_photos' => array_slice($analysis, 0, 30),
            ];
        } catch (\Exception $e) {
            Log::error('AI equipmentImageAnalysis failed', ['error' => $e->getMessage()]);

            return ['total_equipments' => 0, 'with_photos' => 0, 'without_photos' => 0, 'coverage_pct' => 0, 'missing_photos' => []];
        }
    }

    /**
     * 11. Previsão de Demanda — Regressão linear simples
     */
    public function demandForecast(int $tenantId, array $filters = []): array
    {
        try {
            $monthsBack = 12;
            $monthly = [];
            for ($i = $monthsBack - 1; $i >= 0; $i--) {
                $start = now()->subMonths($i)->startOfMonth();
                $end = now()->subMonths($i)->endOfMonth();
                $count = WorkOrder::where('tenant_id', $tenantId)
                    ->whereBetween('created_at', [$start, $end])
                    ->count();
                $monthly[] = [
                    'month' => $start->format('Y-m'),
                    'count' => $count,
                ];
            }

            // Regressão linear simples (y = ax + b)
            $n = count($monthly);
            $xValues = range(1, $n);
            $yValues = array_column($monthly, 'count');

            $sumX = array_sum($xValues);
            $sumY = array_sum($yValues);
            $sumXY = 0;
            $sumX2 = 0;
            for ($i = 0; $i < $n; $i++) {
                $sumXY += $xValues[$i] * $yValues[$i];
                $sumX2 += $xValues[$i] * $xValues[$i];
            }

            $denominator = ($n * $sumX2 - $sumX * $sumX);
            $a = $denominator != 0 ? ($n * $sumXY - $sumX * $sumY) / $denominator : 0;
            $b = ($sumY - $a * $sumX) / $n;

            // Projeção 3 meses
            $forecast = [];
            for ($i = 1; $i <= 3; $i++) {
                $x = $n + $i;
                $predicted = max(0, round($a * $x + $b));
                $forecast[] = [
                    'month' => now()->addMonths($i)->format('Y-m'),
                    'predicted_count' => $predicted,
                ];
            }

            $trend = $a > 0.5 ? 'crescente' : ($a < -0.5 ? 'decrescente' : 'estável');

            return [
                'historical' => $monthly,
                'forecast' => $forecast,
                'trend' => $trend,
                'trend_slope' => round($a, 3),
                'avg_monthly' => $n > 0 ? round($sumY / $n, 1) : 0,
            ];
        } catch (\Exception $e) {
            Log::error('AI demandForecast failed', ['error' => $e->getMessage()]);

            return ['historical' => [], 'forecast' => [], 'trend' => 'unknown', 'trend_slope' => 0, 'avg_monthly' => 0];
        }
    }

    /**
     * 12. Otimização de Rotas com IA — Scoring inteligente
     */
    public function aiRouteOptimization(int $tenantId, array $filters = []): array
    {
        try {
            $pendingOs = WorkOrder::where('tenant_id', $tenantId)
                ->whereIn('status', ['pending', 'scheduled'])
                ->with('customer')
                ->get();

            $scored = $pendingOs->map(function ($wo) {
                $slaScore = match ($wo->priority ?? 'normal') {
                    'urgent', 'critical' => 100,
                    'high' => 75,
                    'normal' => 50,
                    'low' => 25,
                    default => 50,
                };

                $daysWaiting = now()->diffInDays(Carbon::parse($wo->created_at));
                $waitScore = min(100, $daysWaiting * 5);

                $totalScore = ($slaScore * 0.6) + ($waitScore * 0.4);

                return [
                    'work_order_id' => $wo->id,
                    'work_order_number' => $wo->number ?? "OS-{$wo->id}",
                    'customer_name' => $wo->customer?->name ?? $wo->customer?->company_name ?? 'N/A',
                    'priority' => $wo->priority ?? 'normal',
                    'days_waiting' => $daysWaiting,
                    'sla_score' => $slaScore,
                    'wait_score' => round($waitScore, 1),
                    'total_score' => round($totalScore, 1),
                    'has_coordinates' => ! empty($wo->customer?->latitude) && ! empty($wo->customer?->longitude),
                    'latitude' => $wo->customer?->latitude,
                    'longitude' => $wo->customer?->longitude,
                ];
            })->sortByDesc('total_score')->values();

            return [
                'optimized_order' => $scored->take(20)->toArray(),
                'total_pending' => $pendingOs->count(),
                'with_coordinates' => $scored->filter(fn ($s) => $s['has_coordinates'])->count(),
                'without_coordinates' => $scored->filter(fn ($s) => ! $s['has_coordinates'])->count(),
            ];
        } catch (\Exception $e) {
            Log::error('AI aiRouteOptimization failed', ['error' => $e->getMessage()]);

            return ['optimized_order' => [], 'total_pending' => 0, 'with_coordinates' => 0, 'without_coordinates' => 0];
        }
    }

    /**
     * 13. Etiquetagem Inteligente de Tickets
     */
    public function smartTicketLabeling(int $tenantId, array $filters = []): array
    {
        try {
            $calls = ServiceCall::where('tenant_id', $tenantId)
                ->where('created_at', '>=', now()->subMonths(3))
                ->get();

            $labelMap = [
                'calibração' => ['calibração', 'calibrar', 'ajuste', 'metrologia'],
                'manutenção' => ['manutenção', 'reparo', 'conserto', 'quebra', 'defeito'],
                'instalação' => ['instalação', 'instalar', 'montagem', 'setup'],
                'urgente' => ['urgente', 'emergência', 'parada', 'interditada', 'multa'],
                'certificado' => ['certificado', 'laudo', 'documento', 'iso'],
                'orçamento' => ['orçamento', 'preço', 'cotação', 'valor'],
                'reclamação' => ['reclamação', 'insatisf', 'problema', 'erro'],
            ];

            $labeled = [];
            foreach ($calls as $call) {
                $text = strtolower($this->serviceCallText($call));
                $tags = [];

                foreach ($labelMap as $label => $keywords) {
                    foreach ($keywords as $kw) {
                        if (str_contains($text, $kw)) {
                            $tags[] = $label;
                            break;
                        }
                    }
                }

                if (! empty($tags)) {
                    $labeled[] = [
                        'service_call_id' => $call->id,
                        'title' => $call->call_number ? "Chamado {$call->call_number}" : "Chamado #{$call->id}",
                        'suggested_tags' => array_unique($tags),
                        'confidence' => count($tags) >= 2 ? 'alta' : 'média',
                    ];
                }
            }

            // Distribuição de tags
            $tagDistribution = [];
            foreach ($labeled as $item) {
                foreach ($item['suggested_tags'] as $tag) {
                    $tagDistribution[$tag] = ($tagDistribution[$tag] ?? 0) + 1;
                }
            }
            arsort($tagDistribution);

            return [
                'labeled_tickets' => array_slice($labeled, 0, 30),
                'total_analyzed' => $calls->count(),
                'labeled_count' => count($labeled),
                'tag_distribution' => $tagDistribution,
            ];
        } catch (\Exception $e) {
            Log::error('AI smartTicketLabeling failed', ['error' => $e->getMessage()]);

            return ['labeled_tickets' => [], 'total_analyzed' => 0, 'labeled_count' => 0, 'tag_distribution' => []];
        }
    }

    /**
     * 14. Previsão de Cancelamento (Churn)
     */
    public function churnPrediction(int $tenantId, array $filters = []): array
    {
        try {
            $customers = Customer::where('tenant_id', $tenantId)->get();

            $predictions = [];
            foreach ($customers as $customer) {
                $lastOs = WorkOrder::where('customer_id', $customer->id)
                    ->where('tenant_id', $tenantId)
                    ->orderBy('created_at', 'desc')
                    ->first();

                $daysSinceLastOs = $lastOs ? now()->diffInDays(Carbon::parse($lastOs->created_at)) : 999;
                $osCount12m = WorkOrder::where('customer_id', $customer->id)
                    ->where('tenant_id', $tenantId)
                    ->where('created_at', '>=', now()->subYear())
                    ->count();

                // Fatores de risco de churn
                $recencyRisk = min(100, $daysSinceLastOs / 3.65); // 365 dias = 100%
                $frequencyRisk = $osCount12m === 0 ? 100 : max(0, 100 - ($osCount12m * 20));

                // Score composto
                $churnScore = round(($recencyRisk * 0.6) + ($frequencyRisk * 0.4), 1);
                $churnRisk = $churnScore >= 80 ? 'critical' : ($churnScore >= 60 ? 'high' : ($churnScore >= 40 ? 'medium' : 'low'));

                if ($churnScore >= 40) {
                    $predictions[] = [
                        'customer_id' => $customer->id,
                        'customer_name' => $customer->name ?? $customer->company_name ?? "Cliente #{$customer->id}",
                        'days_since_last_os' => $daysSinceLastOs,
                        'os_count_12m' => $osCount12m,
                        'churn_score' => $churnScore,
                        'churn_risk' => $churnRisk,
                        'recommendation' => match ($churnRisk) {
                            'critical' => 'Contato urgente — cliente pode estar migrando para concorrente',
                            'high' => 'Agendar follow-up proativo e oferecer desconto',
                            'medium' => 'Enviar comunicação de manutenção preventiva',
                            default => 'Monitorar',
                        },
                    ];
                }
            }

            usort($predictions, fn ($a, $b) => $b['churn_score'] <=> $a['churn_score']);

            return [
                'predictions' => array_slice($predictions, 0, $filters['limit'] ?? 50),
                'total_customers' => $customers->count(),
                'at_risk_count' => count($predictions),
                'critical_count' => count(array_filter($predictions, fn ($p) => $p['churn_risk'] === 'critical')),
            ];
        } catch (\Exception $e) {
            Log::error('AI churnPrediction failed', ['error' => $e->getMessage()]);

            return ['predictions' => [], 'total_customers' => 0, 'at_risk_count' => 0, 'critical_count' => 0];
        }
    }

    /**
     * 15. Resumos Automatizados de Serviços
     */
    public function serviceSummary(int $tenantId, int $workOrderId): array
    {
        try {
            $wo = WorkOrder::where('tenant_id', $tenantId)
                ->where('id', $workOrderId)
                ->with(['customer', 'items', 'technicians'])
                ->firstOrFail();

            $items = $wo->items ?? collect();
            $technicians = $wo->technicians ?? collect();

            $summary = "📋 **Resumo da OS #{$wo->number}**\n\n";
            $summary .= '**Cliente:** '.($wo->customer?->name ?? $wo->customer?->company_name ?? 'N/A')."\n";
            $summary .= '**Status:** '.ucfirst($wo->status)."\n";
            $summary .= '**Data:** '.Carbon::parse($wo->created_at)->format('d/m/Y')."\n\n";

            if ($technicians->isNotEmpty()) {
                $techNames = $technicians->pluck('name')->join(', ');
                $summary .= "**Técnicos:** {$techNames}\n\n";
            }

            if ($items->isNotEmpty()) {
                $summary .= "**Itens/Serviços ({$items->count()}):**\n";
                foreach ($items->take(10) as $item) {
                    $desc = $item->description ?? $item->name ?? 'Item';
                    $qty = $item->quantity ?? 1;
                    $price = $item->total_price ?? ($item->unit_price * $qty);
                    $summary .= "- {$desc} (Qtd: {$qty}) — R$ ".number_format($price, 2, ',', '.')."\n";
                }
                $total = $items->sum(fn ($i) => $i->total_price ?? ($i->unit_price ?? 0) * ($i->quantity ?? 1));
                $summary .= "\n**Total:** R$ ".number_format($total, 2, ',', '.')."\n";
            }

            if ($wo->observations) {
                $summary .= "\n**Observações:** {$wo->observations}\n";
            }

            return [
                'work_order_id' => $wo->id,
                'work_order_number' => $wo->number ?? "OS-{$wo->id}",
                'summary_text' => $summary,
                'metadata' => [
                    'customer' => $wo->customer?->name ?? $wo->customer?->company_name ?? 'N/A',
                    'status' => $wo->status,
                    'items_count' => $items->count(),
                    'technicians_count' => $technicians->count(),
                    'created_at' => Carbon::parse($wo->created_at)->toDateString(),
                    'completed_at' => $wo->completed_at ? Carbon::parse($wo->completed_at)->toDateString() : null,
                ],
            ];
        } catch (ModelNotFoundException $e) {
            return ['work_order_id' => $workOrderId, 'summary_text' => 'OS não encontrada.', 'metadata' => []];
        } catch (\Exception $e) {
            Log::error('AI serviceSummary failed', ['error' => $e->getMessage()]);

            return ['work_order_id' => $workOrderId, 'summary_text' => 'Erro ao gerar resumo.', 'metadata' => []];
        }
    }

    /**
     * Calcula desvio padrão de um array.
     */
    private function serviceCallText(ServiceCall $call): string
    {
        return trim(implode(' ', array_filter([
            $call->call_number,
            $call->observations,
            $call->resolution_notes,
        ])));
    }

    /** @return array<string, list<string>> */
    private function serviceCallTopicKeywords(): array
    {
        return [
            'calibracao' => ['calibracao', 'calibrar', 'ajuste', 'metrologia'],
            'manutencao' => ['manutencao', 'reparo', 'conserto', 'quebra', 'defeito'],
            'instalacao' => ['instalacao', 'instalar', 'montagem', 'setup'],
            'urgencia' => ['urgente', 'emergencia', 'parada', 'interditada', 'prazo'],
            'documentacao' => ['certificado', 'laudo', 'documento', 'iso'],
            'orcamento' => ['orcamento', 'preco', 'cotacao', 'valor'],
            'reclamacao' => ['reclamacao', 'insatisf', 'problema', 'erro'],
        ];
    }

    private function standardDeviation(array $values): float
    {
        $n = count($values);
        if ($n < 2) {
            return 0;
        }

        $mean = array_sum($values) / $n;
        $sumSquares = array_sum(array_map(fn ($v) => ($v - $mean) ** 2, $values));

        return sqrt($sumSquares / ($n - 1));
    }
}
