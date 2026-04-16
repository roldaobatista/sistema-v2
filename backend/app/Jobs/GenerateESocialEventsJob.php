<?php

namespace App\Jobs;

use App\Models\Payroll;
use App\Services\ESocialService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateESocialEventsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(public Payroll $payroll) {}

    public function handle(ESocialService $service): void
    {
        $service->generatePayrollEvents($this->payroll);
    }

    public function failed(\Throwable $e): void
    {
        Log::error("GenerateESocialEventsJob failed permanently for payroll #{$this->payroll->id}", [
            'tenant_id' => $this->payroll->tenant_id,
            'error' => $e->getMessage(),
        ]);
    }
}
