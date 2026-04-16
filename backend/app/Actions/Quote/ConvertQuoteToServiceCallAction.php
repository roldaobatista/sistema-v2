<?php

namespace App\Actions\Quote;

use App\Enums\QuoteStatus;
use App\Enums\ServiceCallStatus;
use App\Models\AuditLog;
use App\Models\Quote;
use App\Models\ServiceCall;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\DB;

class ConvertQuoteToServiceCallAction
{
    public function execute(Quote $quote, int $userId, bool $isInstallationTesting = false): ServiceCall
    {
        $status = $quote->status;

        if (! $status->isConvertible()) {
            throw new \DomainException('Orçamento precisa estar aprovado (interna ou externamente) para converter em chamado');
        }

        $existingWo = WorkOrder::query()
            ->where('tenant_id', $quote->tenant_id)
            ->where('quote_id', $quote->id)
            ->first();

        if ($existingWo) {
            $woNumber = $existingWo->os_number ?? $existingWo->number;

            throw new \DomainException("Orçamento já convertido na OS #{$woNumber}. Não é possível criar chamado.");
        }

        $existingCall = ServiceCall::query()
            ->where('tenant_id', $quote->tenant_id)
            ->where('quote_id', $quote->id)
            ->first();

        if ($existingCall) {
            throw new \DomainException("Orçamento já convertido no chamado #{$existingCall->call_number}");
        }

        return DB::transaction(function () use ($quote, $userId, $isInstallationTesting, $status) {
            $call = ServiceCall::create([
                'tenant_id' => $quote->tenant_id,
                'call_number' => ServiceCall::nextNumber($quote->tenant_id),
                'customer_id' => $quote->customer_id,
                'quote_id' => $quote->id,
                'status' => ServiceCallStatus::PENDING_SCHEDULING->value,
                'priority' => 'normal',
                'observations' => $quote->observations ?? "Chamado gerado a partir do orçamento {$quote->quote_number}",
                'created_by' => $userId,
            ]);

            $newStatus = $this->resolvePostConversionStatus($status, $isInstallationTesting);
            $quote->update([
                'status' => $newStatus,
                'is_installation_testing' => $isInstallationTesting,
            ]);

            $label = $isInstallationTesting ? 'Chamado (instalação p/ teste)' : 'Chamado';
            AuditLog::log('created', "{$label} {$call->call_number} criado a partir do orçamento {$quote->quote_number}", $call);

            return $call;
        });
    }

    private function resolvePostConversionStatus(QuoteStatus $currentStatus, bool $isInstallationTesting): string
    {
        if ($isInstallationTesting) {
            return QuoteStatus::INSTALLATION_TESTING->value;
        }

        return QuoteStatus::IN_EXECUTION->value;
    }
}
