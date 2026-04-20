<?php

namespace App\Actions\Quote;

use App\Enums\QuoteStatus;
use App\Exceptions\QuoteAlreadyConvertedException;
use App\Models\AuditLog;
use App\Models\Quote;
use App\Models\QuoteEquipment;
use App\Models\QuoteItem;
use App\Models\ServiceCall;
use App\Models\WorkOrder;
use App\Models\WorkOrderItem;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;

class ConvertQuoteToWorkOrderAction
{
    public function execute(Quote $quote, int $userId, bool $isInstallationTesting = false): WorkOrder
    {
        $status = $quote->status;

        if (! $status->isConvertible()) {
            throw new \DomainException('Orçamento precisa estar aprovado (interna ou externamente) para converter');
        }

        $existingWorkOrder = WorkOrder::query()
            ->where('tenant_id', $quote->tenant_id)
            ->where('quote_id', $quote->id)
            ->first();

        if ($existingWorkOrder) {
            throw new QuoteAlreadyConvertedException($existingWorkOrder);
        }

        $existingCall = ServiceCall::query()
            ->where('tenant_id', $quote->tenant_id)
            ->where('quote_id', $quote->id)
            ->first();

        if ($existingCall) {
            throw new \DomainException("Orçamento já convertido no chamado #{$existingCall->call_number}. Não é possível criar OS.");
        }

        return DB::transaction(function () use ($quote, $userId, $isInstallationTesting, $status) {
            $quote->load([
                'equipments.items.product:id,name',
                'equipments.items.service:id,name',
            ]);

            $primaryEquipmentId = $quote->equipments
                ->pluck('equipment_id')
                ->filter()
                ->map(fn (mixed $id): int => (int) $id)
                ->first();

            $quoteDiscountPercentage = (float) ($quote->discount_percentage ?? 0);
            $quoteFixedDiscount = $quoteDiscountPercentage > 0
                ? '0.00'
                : (string) ($quote->discount_amount ?? '0.00');

            $wo = WorkOrder::create([
                'tenant_id' => $quote->tenant_id,
                'number' => WorkOrder::nextNumber($quote->tenant_id),
                'customer_id' => $quote->customer_id,
                'equipment_id' => $primaryEquipmentId,
                'quote_id' => $quote->id,
                'origin_type' => WorkOrder::ORIGIN_QUOTE,
                'lead_source' => $quote->source,
                'seller_id' => $quote->seller_id,
                'assigned_to' => $quote->seller_id,
                'created_by' => $userId,
                'status' => WorkOrder::STATUS_OPEN,
                'priority' => WorkOrder::PRIORITY_MEDIUM,
                'description' => $quote->observations ?? "Gerada a partir do orçamento {$quote->quote_number}",
                'discount' => $quoteFixedDiscount,
                'discount_percentage' => $quoteDiscountPercentage,
                'displacement_value' => $quote->displacement_value ?? 0,
                'total' => $quote->total,
            ]);

            // Vincular o seller do orçamento como técnico da OS para visibilidade no PWA
            if ($quote->seller_id) {
                $wo->technicians()->syncWithoutDetaching([
                    $quote->seller_id => ['tenant_id' => $wo->tenant_id],
                ]);
            }

            $wo->statusHistory()->create([
                'tenant_id' => $quote->tenant_id,
                'user_id' => $userId,
                'from_status' => null,
                'to_status' => WorkOrder::STATUS_OPEN,
                'notes' => "OS criada a partir do orçamento {$quote->quote_number}",
            ]);

            /** @var QuoteEquipment $eq */
            foreach ($quote->equipments as $eq) {
                foreach ($eq->items as $item) {
                    /** @var QuoteItem $item */
                    $preDiscountTotal = bcmul($this->decimal($item->quantity), $this->decimal($item->unit_price), 2);
                    $lineSubtotal = $this->decimal($item->subtotal);
                    $discountAmount = bcsub($preDiscountTotal, $lineSubtotal, 2);
                    if (bccomp($discountAmount, '0', 2) < 0) {
                        $discountAmount = '0.00';
                    }

                    $wo->items()->create([
                        'tenant_id' => $quote->tenant_id,
                        'type' => $item->type,
                        'reference_id' => $item->type === WorkOrderItem::TYPE_PRODUCT ? $item->product_id : $item->service_id,
                        'description' => $item->custom_description
                            ?: ($item->product->name ?? $item->service->name ?? 'Item de orçamento'),
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price,
                        'discount' => $discountAmount,
                    ]);
                }

                if ($eq->equipment_id) {
                    $wo->equipmentsList()->syncWithoutDetaching([
                        $eq->equipment_id => [
                            'observations' => $eq->description ?? '',
                            'tenant_id' => $wo->tenant_id,
                        ],
                    ]);
                }
            }

            $newStatus = $this->resolvePostConversionStatus($status, $isInstallationTesting);
            $quote->update([
                'status' => $newStatus,
                'is_installation_testing' => $isInstallationTesting,
            ]);

            $label = $isInstallationTesting ? 'OS (instalação p/ teste)' : 'OS';
            AuditLog::log('created', "{$label} criada a partir do orçamento {$quote->quote_number}", $wo);

            return $wo;
        });
    }

    private function resolvePostConversionStatus(QuoteStatus $currentStatus, bool $isInstallationTesting): string
    {
        if ($isInstallationTesting) {
            return QuoteStatus::INSTALLATION_TESTING->value;
        }

        return QuoteStatus::IN_EXECUTION->value;
    }

    /**
     * @return numeric-string
     */
    private function decimal(float|int|string|null $value): string
    {
        return Decimal::string($value);
    }
}
