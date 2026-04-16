<?php

namespace App\Actions\Quote;

use App\Models\Quote;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class UpdateQuoteAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(Quote $quote, array $data, ?User $user = null): Quote
    {
        $status = $quote->status;
        if (! $status->isMutable()) {
            throw new \DomainException('Só é possível editar orçamentos em rascunho, aprovação interna pendente, rejeitados ou em renegociação');
        }

        if ($user) {
            $this->ensureCanApplyDiscount($user, $data);
        }

        return DB::transaction(function () use ($quote, $data) {
            $quote->update($data);
            $quote->increment('revision');
            $quote->recalculateTotal();

            return $quote;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function ensureCanApplyDiscount(User $user, array $data): void
    {
        $discountPercentage = (float) ($data['discount_percentage'] ?? 0);
        $discountAmount = (float) ($data['discount_amount'] ?? 0);

        if ($discountPercentage <= 0 && $discountAmount <= 0) {
            return;
        }

        if ($user->can('quotes.quote.apply_discount') || $user->can('os.work_order.apply_discount')) {
            return;
        }

        throw new AuthorizationException('Apenas gerentes/admin podem aplicar descontos.');
    }
}
