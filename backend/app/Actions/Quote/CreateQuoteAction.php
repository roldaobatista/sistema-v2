<?php

namespace App\Actions\Quote;

use App\Enums\QuoteStatus;
use App\Models\AuditLog;
use App\Models\Quote;
use App\Models\QuoteEquipment;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class CreateQuoteAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, int $tenantId, int $userId): Quote
    {
        $user = User::find($userId);
        if ($user) {
            $this->ensureCanApplyDiscount($user, $data);
        }

        return DB::transaction(function () use ($data, $tenantId, $userId) {
            $equipments = $data['equipments'] ?? [];

            $quote = Quote::create([
                'tenant_id' => $tenantId,
                'quote_number' => Quote::nextNumber($tenantId),
                'customer_id' => $data['customer_id'],
                'seller_id' => $data['seller_id'] ?? $userId,
                'created_by' => $userId,
                'status' => QuoteStatus::DRAFT->value,
                'source' => $data['source'] ?? null,
                'valid_until' => $data['valid_until'] ?? null,
                'discount_percentage' => $data['discount_percentage'] ?? 0,
                'discount_amount' => $data['discount_amount'] ?? 0,
                'displacement_value' => $data['displacement_value'] ?? 0,
                'observations' => $data['observations'] ?? $data['description'] ?? $data['title'] ?? null,
                'internal_notes' => $data['internal_notes'] ?? null,
                'payment_terms' => $data['payment_terms'] ?? null,
                'payment_terms_detail' => $data['payment_terms_detail'] ?? null,
                'template_id' => $data['template_id'] ?? null,
                'opportunity_id' => $data['opportunity_id'] ?? null,
                'currency' => $data['currency'] ?? 'BRL',
                'custom_fields' => $data['custom_fields'] ?? null,
                'general_conditions' => $data['general_conditions'] ?? null,
            ]);

            foreach ($equipments as $i => $eqData) {
                /** @var QuoteEquipment $eq */
                $eq = $quote->equipments()->create([
                    'tenant_id' => $tenantId,
                    'equipment_id' => $eqData['equipment_id'],
                    'description' => $eqData['description'] ?? null,
                    'sort_order' => $i,
                ]);

                foreach ($eqData['items'] as $j => $itemData) {
                    $eq->items()->create([
                        'tenant_id' => $tenantId,
                        ...Arr::only($itemData, [
                            'type', 'product_id', 'service_id', 'custom_description',
                            'quantity', 'original_price', 'cost_price', 'unit_price',
                            'discount_percentage', 'internal_note',
                        ]),
                        'sort_order' => $j,
                    ]);
                }
            }

            $quote->recalculateTotal();
            AuditLog::log('created', "Orçamento {$quote->quote_number} criado", $quote);

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
