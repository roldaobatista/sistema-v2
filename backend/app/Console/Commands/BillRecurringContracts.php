<?php

namespace App\Console\Commands;

use App\Models\AccountReceivable;
use App\Models\Notification;
use App\Models\RecurringContract;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BillRecurringContracts extends Command
{
    protected $signature = 'contracts:bill-recurring';

    protected $description = 'Generate accounts receivable for active recurring contracts';

    public function handle(): int
    {
        $tenants = Tenant::where('status', Tenant::STATUS_ACTIVE)->get();
        $totalBilled = 0;

        foreach ($tenants as $tenant) {
            try {
                app()->instance('current_tenant_id', $tenant->id);

                $contracts = RecurringContract::where('tenant_id', $tenant->id)
                    ->where('is_active', true)
                    ->where('billing_type', 'fixed_monthly')
                    ->where('monthly_value', '>', 0)
                    ->get();

                // Query admins once per tenant instead of per contract
                $admins = User::where('tenant_id', $tenant->id)
                    ->whereHas('roles', fn ($q) => $q->whereIn('name', [Role::SUPER_ADMIN, Role::ADMIN, Role::FINANCEIRO]))
                    ->get();

                foreach ($contracts as $contract) {
                    try {
                        $period = now()->format('Y-m');
                        $description = "Contrato Recorrente: {$contract->name} - ".now()->format('m/Y');
                        $billingKey = "recurring_contract:{$contract->id}:{$period}";

                        // Idempotency check inside transaction to prevent TOCTOU duplicates
                        $created = DB::transaction(function () use ($tenant, $contract, $description, $billingKey) {
                            $alreadyBilled = AccountReceivable::where('tenant_id', $tenant->id)
                                ->where('notes', $billingKey)
                                ->lockForUpdate()
                                ->exists();

                            if ($alreadyBilled) {
                                return false;
                            }

                            AccountReceivable::create([
                                'tenant_id' => $tenant->id,
                                'customer_id' => $contract->customer_id,
                                'created_by' => $contract->created_by,
                                'description' => $description,
                                'notes' => $billingKey,
                                'amount' => $contract->monthly_value,
                                'amount_paid' => 0,
                                'due_date' => now()->endOfMonth(),
                                'status' => AccountReceivable::STATUS_PENDING,
                            ]);

                            return true;
                        });

                        if (! $created) {
                            continue;
                        }

                        $totalBilled++;

                        foreach ($admins as $admin) {
                            try {
                                Notification::notify(
                                    $tenant->id,
                                    $admin->id,
                                    'contract_billed',
                                    'Contrato Faturado',
                                    [
                                        'message' => "Contrato {$contract->name} faturado automaticamente (R$ ".number_format((float) $contract->monthly_value, 2, ',', '.').').',
                                        'icon' => 'repeat',
                                        'color' => 'info',
                                    ]
                                );
                            } catch (\Throwable $e) {
                                Log::warning("BillRecurringContracts: notificação falhou para contract #{$contract->id}, user #{$admin->id}", ['error' => $e->getMessage()]);
                            }
                        }
                    } catch (\Throwable $e) {
                        Log::error("BillRecurringContracts: falha ao faturar contrato #{$contract->id}", ['error' => $e->getMessage()]);
                    }
                }
            } catch (\Throwable $e) {
                Log::error("BillRecurringContracts: falha no tenant #{$tenant->id}", ['error' => $e->getMessage()]);
                $this->error("Tenant #{$tenant->id}: {$e->getMessage()}");
            }
        }

        $this->info("{$totalBilled} contratos faturados com sucesso.");

        return self::SUCCESS;
    }
}
