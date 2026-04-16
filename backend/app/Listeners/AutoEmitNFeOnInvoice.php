<?php

namespace App\Listeners;

use App\Events\InvoiceCreated;
use App\Jobs\EmitNFeJob;
use App\Models\SystemSetting;
use App\Models\Tenant;
use App\Models\TenantSetting;

class AutoEmitNFeOnInvoice
{
    public function handle(InvoiceCreated $event): void
    {
        $invoice = $event->invoice;
        $tenantId = $invoice->tenant_id;

        $autoEmit = SystemSetting::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('key', 'auto_emit_nfe')
            ->value('value');

        if (! $autoEmit || strtolower((string) $autoEmit) === 'false' || strtolower((string) $autoEmit) === '0') {
            return;
        }

        $tenant = Tenant::find($tenantId);

        // Verifica provedor (mock verificação baseada no fiscal_provider configurado)
        $hasProvider = TenantSetting::where('tenant_id', $tenantId)
            ->where('key', 'fiscal_provider')
            ->exists();

        // Enfileira o job com retry exponencial
        EmitNFeJob::dispatch($invoice->id)->onQueue('fiscal');
    }
}
