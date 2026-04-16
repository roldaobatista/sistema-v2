<?php

namespace App\Actions\Quote;

use App\Enums\QuoteStatus;
use App\Events\QuoteApproved;
use App\Models\AuditLog;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ApproveQuoteAction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(
        Quote $quote,
        ?User $actor = null,
        array $attributes = [],
        ?string $auditDescription = null,
    ): Quote {
        if ($quote->status !== QuoteStatus::SENT) {
            throw new \DomainException('Orçamento precisa estar enviado para aprovar');
        }

        return DB::transaction(function () use ($quote, $actor, $attributes, $auditDescription) {
            // Lock para evitar aprovação/rejeição concorrente
            $locked = Quote::lockForUpdate()->findOrFail($quote->id);

            if ($locked->status !== QuoteStatus::SENT) {
                throw new \DomainException('Orçamento precisa estar enviado para aprovar');
            }

            $locked->update(array_merge([
                'status' => QuoteStatus::APPROVED->value,
                'approved_at' => now(),
                'rejected_at' => null,
                'rejection_reason' => null,
            ], $attributes));

            AuditLog::log(
                'status_changed',
                $auditDescription ?? "Orçamento {$locked->quote_number} aprovado",
                $locked
            );

            $approver = $actor ?? $this->resolveApprovalActor($locked);
            if ($approver) {
                QuoteApproved::dispatch($locked->fresh(['customer', 'seller']), $approver);
            }

            // Sync original model so callers see updated state
            $quote->refresh();

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
