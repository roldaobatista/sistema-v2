<?php

namespace App\Actions\Quote;

use App\Enums\QuoteStatus;
use App\Events\QuoteApproved;
use App\Models\AuditLog;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ApproveAfterTestAction
{
    public function execute(Quote $quote, int $userId): Quote
    {
        $rawStatus = $quote->status->value;

        if ($rawStatus !== QuoteStatus::INSTALLATION_TESTING->value) {
            throw new \DomainException('Apenas orçamentos em "Instalação p/ Teste" podem ser aprovados pelo cliente.');
        }

        return DB::transaction(function () use ($quote) {
            $quote->update([
                'status' => QuoteStatus::APPROVED->value,
                'approved_at' => now(),
            ]);

            AuditLog::log('updated', "Orçamento {$quote->quote_number} aprovado pelo cliente após teste", $quote);

            $quote->refresh();
            $approver = $this->resolveApprovalActor($quote->load(['customer', 'seller']));
            if ($approver) {
                QuoteApproved::dispatch($quote, $approver);
            }

            return $quote;
        });
    }

    private function resolveApprovalActor(Quote $quote): ?User
    {
        if ($quote->relationLoaded('seller') && $quote->seller) {
            return $quote->seller;
        }

        if ($quote->seller_id) {
            $seller = User::where('tenant_id', $quote->tenant_id)
                ->where('id', $quote->seller_id)
                ->first();
            if ($seller) {
                return $seller;
            }
        }

        return User::where('tenant_id', $quote->tenant_id)->orderBy('id')->first();
    }
}
