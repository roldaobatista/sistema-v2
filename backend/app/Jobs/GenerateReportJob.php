<?php

namespace App\Jobs;

use App\Models\Notification;
use App\Services\CashFlowProjectionService;
use App\Services\DREService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Gera relatórios pesados em background e notifica o usuário quando pronto.
 * Tipos: 'dre', 'cash_flow', 'expenses', 'receivables', 'payables'
 */
class GenerateReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public int $backoff = 30;

    public function __construct(
        public int $tenantId,
        public int $userId,
        public string $reportType,
        public string $from,
        public string $to,
        public array $filters = [],
    ) {
        $this->queue = 'reports';
    }

    public function handle(DREService $dreService, CashFlowProjectionService $cashFlowService): void
    {
        app()->instance('current_tenant_id', $this->tenantId);

        $fromDate = Carbon::parse($this->from);
        $toDate = Carbon::parse($this->to);

        try {
            $data = match ($this->reportType) {
                'dre' => $dreService->generate($fromDate, $toDate, $this->tenantId),
                'cash_flow' => $cashFlowService->project($fromDate, $toDate, $this->tenantId),
                default => $this->generateGenericReport(),
            };

            // Salvar como JSON para download
            $filename = "reports/{$this->tenantId}/{$this->reportType}_{$this->from}_{$this->to}.json";
            Storage::put($filename, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            // Notificar usuário
            Notification::notify(
                $this->tenantId,
                $this->userId,
                'report_ready',
                'Relatório Pronto',
                [
                    'message' => "O relatório {$this->getReportLabel()} ({$fromDate->format('d/m/Y')} - {$toDate->format('d/m/Y')}) está pronto para download.",
                    'icon' => 'file-text',
                    'color' => 'success',
                    'data' => [
                        'report_type' => $this->reportType,
                        'file_path' => $filename,
                    ],
                ]
            );

            Log::info("Relatório {$this->reportType} gerado com sucesso", [
                'tenant_id' => $this->tenantId,
                'user_id' => $this->userId,
                'file' => $filename,
            ]);
        } catch (\Throwable $e) {
            Log::error("Falha ao gerar relatório {$this->reportType}: {$e->getMessage()}");

            Notification::notify(
                $this->tenantId,
                $this->userId,
                'report_failed',
                'Falha no Relatório',
                [
                    'message' => "O relatório {$this->getReportLabel()} falhou. Tente novamente.",
                    'icon' => 'alert-triangle',
                    'color' => 'danger',
                    'data' => ['error' => $e->getMessage()],
                ]
            );

            throw $e;
        }
    }

    private function generateGenericReport(): array
    {
        return [
            'type' => $this->reportType,
            'period' => ['from' => $this->from, 'to' => $this->to],
            'generated_at' => now()->toDateTimeString(),
            'filters' => $this->filters,
            'data' => [],
        ];
    }

    public function failed(\Throwable $e): void
    {
        Log::error("GenerateReportJob failed permanently: {$this->reportType}", [
            'tenant_id' => $this->tenantId,
            'user_id' => $this->userId,
            'error' => $e->getMessage(),
        ]);

        Notification::notify(
            $this->tenantId,
            $this->userId,
            'report_failed',
            'Falha no Relatório',
            [
                'message' => "O relatório {$this->getReportLabel()} falhou permanentemente.",
                'icon' => 'alert-triangle',
                'color' => 'danger',
            ]
        );
    }

    private function getReportLabel(): string
    {
        return match ($this->reportType) {
            'dre' => 'DRE',
            'cash_flow' => 'Fluxo de Caixa',
            'expenses' => 'Despesas',
            'receivables' => 'Contas a Receber',
            'payables' => 'Contas a Pagar',
            default => ucfirst($this->reportType),
        };
    }
}
