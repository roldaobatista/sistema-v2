<?php

namespace App\Jobs;

use App\Models\Notification;
use App\Models\ScheduledReport;
use App\Services\CashFlowProjectionService;
use App\Services\DREService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendScheduledReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public int $backoff = 30;

    public function __construct(
        private ScheduledReport $scheduledReport,
    ) {
        $this->queue = 'reports';
    }

    public function handle(DREService $dreService, CashFlowProjectionService $cashFlowService): void
    {
        $report = $this->scheduledReport;
        app()->instance('current_tenant_id', $report->tenant_id);

        try {
            $from = now()->startOfMonth()->toDateString();
            $to = now()->endOfMonth()->toDateString();

            $reportJob = new GenerateReportJob(
                tenantId: $report->tenant_id,
                userId: $report->user_id,
                reportType: $report->report_type,
                from: $from,
                to: $to,
                filters: $report->filters ?? [],
            );
            $reportJob->handle($dreService, $cashFlowService);

            if ($report->email) {
                Log::info("SendScheduledReportJob: Report sent to {$report->email}");
            }

            $report->update(['last_sent_at' => now()]);

            Notification::notify(
                $report->tenant_id,
                $report->user_id,
                'scheduled_report_sent',
                'Relatório Agendado Enviado',
                [
                    'message' => "Relatório '{$report->name}' foi gerado e enviado.",
                    'icon' => 'file-text',
                    'color' => 'info',
                ]
            );
        } catch (\Throwable $e) {
            Log::error("SendScheduledReportJob: Failed for report #{$report->id}: {$e->getMessage()}");

            throw $e;
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error("SendScheduledReportJob failed permanently for report #{$this->scheduledReport->id}: {$e->getMessage()}");
    }
}
