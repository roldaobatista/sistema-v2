<?php

namespace App\Jobs;

use App\Models\Import;
use App\Services\ImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;

    public int $tries = 2;

    public array $backoff = [60, 300];

    public function __construct(
        protected Import $import
    ) {
        $this->queue = 'imports';
    }

    public function handle(ImportService $importService): void
    {
        if ($this->import->tenant_id) {
            app()->instance('current_tenant_id', $this->import->tenant_id);
        }

        try {
            Log::info("Iniciando ImportJob para import #{$this->import->id}");
            $importService->processImport($this->import);
            Log::info("ImportJob finalizado com sucesso para import #{$this->import->id}");
        } catch (\Throwable $e) {
            Log::error("Falha no ImportJob #{$this->import->id}: ".$e->getMessage());

            $this->import->update([
                'status' => Import::STATUS_FAILED,
                'error_log' => array_merge($this->import->error_log ?? [], [[
                    'line' => 0,
                    'message' => 'Erro crítico no processamento: '.$e->getMessage(),
                    'data' => [],
                ]]),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error("ImportJob #{$this->import->id} failed permanently: {$e->getMessage()}");

        $this->import->update([
            'status' => Import::STATUS_FAILED,
            'error_log' => array_merge($this->import->error_log ?? [], [[
                'line' => 0,
                'message' => 'Falha permanente após todas as tentativas: '.$e->getMessage(),
                'data' => [],
            ]]),
        ]);
    }
}
