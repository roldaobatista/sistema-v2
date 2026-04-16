<?php

namespace App\Services;

use App\Services\Integration\IntegrationHealthService;
use Illuminate\Support\Facades\Log;

/**
 * AI Conversational Assistant with tool calling.
 *
 * Translates natural language questions into structured data queries
 * using the AIAnalyticsService and IntegrationHealthService as "tools".
 */
class AiAssistantService
{
    /**
     * Tool definitions exposed to the LLM.
     *
     * @var array<string, array{description: string, parameters: array}>
     */
    private const TOOLS = [
        'predictive_maintenance' => [
            'description' => 'Analisa o histórico de calibrações de equipamentos e prevê próximas manutenções. Retorna equipamentos com risco de atraso.',
            'parameters' => [],
        ],
        'expense_analysis' => [
            'description' => 'Analisa despesas em aberto e detecta padrões de gasto. Útil para perguntas sobre custos e gastos.',
            'parameters' => [],
        ],
        'triage_suggestions' => [
            'description' => 'Sugere atribuição inteligente de ordens de serviço a técnicos baseado em habilidades e carga de trabalho.',
            'parameters' => [],
        ],
        'sentiment_analysis' => [
            'description' => 'Analisa sentimento das interações com clientes baseado em pesquisas de satisfação e histórico de chamados.',
            'parameters' => [],
        ],
        'dynamic_pricing' => [
            'description' => 'Sugere precificação dinâmica baseada em dados históricos, margem e demanda.',
            'parameters' => [],
        ],
        'financial_anomalies' => [
            'description' => 'Detecta anomalias financeiras: contas vencidas, variações bruscas, concentração de receita.',
            'parameters' => [],
        ],
        'natural_language_report' => [
            'description' => 'Gera um resumo em linguagem natural dos principais indicadores do sistema (KPIs).',
            'parameters' => [],
        ],
        'customer_clustering' => [
            'description' => 'Agrupa clientes por comportamento: receita, frequência de serviço, tempo de relacionamento.',
            'parameters' => [],
        ],
        'demand_forecast' => [
            'description' => 'Previsão de demanda futura baseada em tendências históricas de ordens de serviço.',
            'parameters' => [],
        ],
        'route_optimization' => [
            'description' => 'Sugere otimização de rotas para técnicos em campo baseado em localização das OSs.',
            'parameters' => [],
        ],
        'smart_ticket_labeling' => [
            'description' => 'Classifica chamados de serviço por urgência, tipo e complexidade automaticamente.',
            'parameters' => [],
        ],
        'churn_prediction' => [
            'description' => 'Prevê risco de churn (perda) de clientes baseado em inatividade, insatisfação e padrões.',
            'parameters' => [],
        ],
        'integration_health' => [
            'description' => 'Mostra o status de saúde das integrações externas (Auvo, FocusNFe, APIs CNPJ, IMAP). Detecta serviços degradados.',
            'parameters' => [],
        ],
    ];

    public function __construct(
        private readonly AIAnalyticsService $analytics,
        private readonly IntegrationHealthService $health,
    ) {}

    /**
     * Process a user message and return an AI-generated response.
     *
     * @param  string  $message  The user's natural language question
     * @return array{answer: string, tools_used: string[], data: array}
     */
    public function chat(string $message, int $tenantId): array
    {
        $toolsUsed = [];
        $toolResults = [];

        // Step 1: Determine which tools to call based on keywords
        $selectedTools = $this->selectTools($message);

        if (empty($selectedTools)) {
            return [
                'answer' => $this->generateSummaryAnswer($message, $tenantId),
                'tools_used' => ['natural_language_report'],
                'data' => [],
            ];
        }

        // Step 2: Execute the selected tools
        foreach ($selectedTools as $toolName) {
            try {
                $result = $this->executeTool($toolName, $tenantId);
                $toolResults[$toolName] = $result;
                $toolsUsed[] = $toolName;
            } catch (\Throwable $e) {
                Log::warning('AiAssistant: tool execution failed', [
                    'tool' => $toolName,
                    'error' => $e->getMessage(),
                ]);
                $toolResults[$toolName] = ['error' => $e->getMessage()];
            }
        }

        // Step 3: Format the response
        $answer = $this->formatAnswer($message, $toolResults);

        return [
            'answer' => $answer,
            'tools_used' => $toolsUsed,
            'data' => $toolResults,
        ];
    }

    /**
     * List available tools for discovery by the frontend.
     *
     * @return array<string, array>
     */
    public function listTools(): array
    {
        return self::TOOLS;
    }

    /**
     * Select tools based on the user message using keyword matching.
     *
     * @return string[]
     */
    private function selectTools(string $message): array
    {
        $msg = mb_strtolower($message);
        $selected = [];

        $mappings = [
            'predictive_maintenance' => ['manutenção', 'calibração', 'calibraçao', 'manutencao', 'equipamento', 'preditiva', 'vencendo', 'próxima calibração'],
            'expense_analysis' => ['despesa', 'gasto', 'custo', 'expense', 'reembolso'],
            'triage_suggestions' => ['triagem', 'atribuir', 'técnico', 'tecnico', 'escalar', 'quem deve'],
            'sentiment_analysis' => ['satisfação', 'satisfacao', 'sentimento', 'reclamação', 'nps', 'feedback'],
            'dynamic_pricing' => ['preço', 'preco', 'precificação', 'margem', 'pricing', 'tabela de preço'],
            'financial_anomalies' => ['anomalia', 'financeiro', 'inadimplência', 'vencido', 'concentração receita', 'fluxo caixa'],
            'natural_language_report' => ['resumo', 'relatório', 'kpi', 'indicadores', 'como está', 'overview', 'visão geral'],
            'customer_clustering' => ['segmentação', 'cluster', 'agrupamento', 'perfil cliente', 'tipo de cliente'],
            'demand_forecast' => ['previsão', 'demanda', 'forecast', 'tendência', 'projeção', 'futuro'],
            'route_optimization' => ['rota', 'otimizar rota', 'logística', 'deslocamento', 'viagem'],
            'smart_ticket_labeling' => ['chamado', 'ticket', 'classificar', 'prioridade', 'urgente', 'service call'],
            'churn_prediction' => ['churn', 'perda', 'cliente inativo', 'risco de perda', 'retenção'],
            'integration_health' => ['integração', 'integracao', 'saúde', 'api', 'circuit breaker', 'auvo', 'focusnfe', 'status sistema'],
        ];

        foreach ($mappings as $tool => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($msg, $keyword)) {
                    $selected[] = $tool;
                    break;
                }
            }
        }

        return array_unique($selected);
    }

    /**
     * Execute a single tool.
     */
    private function executeTool(string $toolName, int $tenantId): array
    {
        return match ($toolName) {
            'predictive_maintenance' => $this->analytics->predictiveMaintenance($tenantId),
            'expense_analysis' => $this->analytics->expenseOcrAnalysis($tenantId),
            'triage_suggestions' => $this->analytics->triageSuggestions($tenantId),
            'sentiment_analysis' => $this->analytics->sentimentAnalysis($tenantId),
            'dynamic_pricing' => $this->analytics->dynamicPricing($tenantId),
            'financial_anomalies' => $this->analytics->financialAnomalies($tenantId),
            'natural_language_report' => $this->analytics->naturalLanguageReport($tenantId),
            'customer_clustering' => $this->analytics->customerClustering($tenantId),
            'demand_forecast' => $this->analytics->demandForecast($tenantId),
            'route_optimization' => $this->analytics->aiRouteOptimization($tenantId),
            'smart_ticket_labeling' => $this->analytics->smartTicketLabeling($tenantId),
            'churn_prediction' => $this->analytics->churnPrediction($tenantId),
            'integration_health' => $this->health->getHealthStatus(),
            default => throw new \InvalidArgumentException("Unknown tool: {$toolName}"),
        };
    }

    /**
     * Generate a summary answer when no specific tool is matched.
     */
    private function generateSummaryAnswer(string $message, int $tenantId): string
    {
        $report = $this->analytics->naturalLanguageReport($tenantId);

        return $report['summary'] ?? 'Não encontrei dados suficientes para responder. Tente perguntar sobre: manutenção preditiva, despesas, satisfação de clientes, anomalias financeiras, previsão de demanda, ou saúde das integrações.';
    }

    /**
     * Format tool results into a human-readable answer.
     */
    private function formatAnswer(string $message, array $toolResults): string
    {
        $parts = [];

        foreach ($toolResults as $tool => $result) {
            if (isset($result['error'])) {
                $parts[] = "⚠️ **{$tool}**: Erro ao consultar — {$result['error']}";
                continue;
            }

            $toolLabel = self::TOOLS[$tool]['description'] ?? $tool;

            switch ($tool) {
                case 'predictive_maintenance':
                    $items = $result['predictions'] ?? $result;
                    $critical = array_filter(is_array($items) ? $items : [], fn ($i) => ($i['risk_level'] ?? '') === 'critical');
                    $high = array_filter(is_array($items) ? $items : [], fn ($i) => ($i['risk_level'] ?? '') === 'high');
                    $parts[] = '🔧 **Manutenção Preditiva**: '.count($critical).' equipamentos em risco crítico, '.count($high).' em risco alto.';
                    break;

                case 'financial_anomalies':
                    $anomalyCount = count($result['anomalies'] ?? $result);
                    $parts[] = "💰 **Anomalias Financeiras**: {$anomalyCount} anomalia(s) detectada(s).";
                    break;

                case 'churn_prediction':
                    $atRisk = array_filter($result['predictions'] ?? $result, fn ($i) => ($i['risk_level'] ?? '') !== 'low');
                    $parts[] = '📉 **Risco de Churn**: '.count($atRisk).' cliente(s) com risco médio ou alto de perda.';
                    break;

                case 'integration_health':
                    $overall = $result['overall'] ?? 'unknown';
                    $down = $result['summary']['down'] ?? 0;
                    $emoji = $overall === 'healthy' ? '✅' : ($overall === 'degraded' ? '🔴' : '⚠️');
                    $parts[] = "{$emoji} **Saúde Integrações**: {$overall} ({$down} serviço(s) fora do ar).";
                    break;

                case 'natural_language_report':
                    $parts[] = '📊 '.($result['summary'] ?? 'Relatório gerado com sucesso.');
                    break;

                default:
                    $count = is_array($result) ? count($result) : 0;
                    $parts[] = '📋 **'.ucfirst(str_replace('_', ' ', $tool))."**: {$count} registro(s) encontrado(s).";
            }
        }

        return implode("\n\n", $parts);
    }
}
