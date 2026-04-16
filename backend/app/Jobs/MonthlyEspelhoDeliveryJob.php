<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Models\User;
use App\Notifications\EspelhoAvailableNotification;
use App\Services\EspelhoPontoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MonthlyEspelhoDeliveryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public ?int $tenantId = null,
        public ?string $referenceMonth = null
    ) {}

    public function handle(EspelhoPontoService $espelhoService): void
    {
        $referenceMonth = $this->referenceMonth ?? now()->subMonth()->format('Y-m');

        $tenants = $this->tenantId
            ? Tenant::where('id', $this->tenantId)->get()
            : Tenant::all();

        foreach ($tenants as $tenant) {
            $this->processForTenant($tenant, $referenceMonth, $espelhoService);
        }
    }

    private function processForTenant(Tenant $tenant, string $referenceMonth, EspelhoPontoService $espelhoService): void
    {
        $employees = User::where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['employee', 'technician']))
            ->get();

        foreach ($employees as $employee) {
            try {
                $this->processForEmployee($tenant, $employee, $referenceMonth, $espelhoService);
            } catch (\Exception $e) {
                Log::error('MonthlyEspelho: failed for user '.$employee->id, [
                    'tenant_id' => $tenant->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function processForEmployee(Tenant $tenant, User $employee, string $referenceMonth, EspelhoPontoService $espelhoService): void
    {
        // Check if confirmation already exists
        $existing = DB::table('espelho_confirmations')
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $employee->id)
            ->where('reference_month', $referenceMonth)
            ->exists();

        if ($existing) {
            return;
        }

        // Create confirmation record
        $confirmationId = DB::table('espelho_confirmations')->insertGetId([
            'tenant_id' => $tenant->id,
            'user_id' => $employee->id,
            'reference_month' => $referenceMonth,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Notify employee
        $employee->notify(new EspelhoAvailableNotification($referenceMonth, $confirmationId));
    }
}
