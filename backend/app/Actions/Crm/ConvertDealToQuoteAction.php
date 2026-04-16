<?php

namespace App\Actions\Crm;

use App\Models\CrmActivity;
use App\Models\CrmDeal;
use App\Models\Quote;
use App\Models\QuoteEquipment;
use App\Models\QuoteItem;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ConvertDealToQuoteAction
{
    /**
     * @return mixed
     */
    public function execute(CrmDeal $deal, User $user, int $tenantId)
    {
        if ($deal->tenant_id !== $tenantId) {
            return ApiResponse::message('Negócio não encontrado.', 404);
        }
        if ($deal->status === CrmDeal::STATUS_LOST) {
            return ApiResponse::message('Não é possível converter um negócio perdido em orçamento.', 422);
        }
        if (! $deal->customer_id) {
            return ApiResponse::message('O negócio precisa ter um cliente vinculado para criar o orçamento.', 422);
        }
        if ($deal->quote_id) {
            return ApiResponse::data([
                'message' => 'Este negócio já possui um orçamento vinculado.',
                'quote_id' => $deal->quote_id,
            ], 422);
        }

        try {
            $quote = DB::transaction(function () use ($deal, $user) {
                $deal->load('products');

                $quoteSource = null;
                $quoteSourceMap = [
                    'indicacao' => 'indicacao',
                    'prospeccao' => 'prospeccao',
                    'retorno' => 'retorno',
                ];
                if ($deal->source && isset($quoteSourceMap[$deal->source])) {
                    $quoteSource = $quoteSourceMap[$deal->source];
                }

                $quote = Quote::create([
                    'tenant_id' => $deal->tenant_id,
                    'quote_number' => Quote::nextNumber($deal->tenant_id),
                    'revision' => 1,
                    'customer_id' => $deal->customer_id,
                    'seller_id' => $deal->assigned_to ?? $user->id,
                    'created_by' => $user->id,
                    'status' => Quote::STATUS_DRAFT,
                    'source' => $quoteSource,
                    'observations' => $deal->notes
                        ? $deal->notes."\n\nGerado a partir do negócio: {$deal->title}"
                        : "Gerado a partir do negócio: {$deal->title}",
                    'valid_until' => now()->addDays(30),
                    'subtotal' => '0.00',
                    'total' => '0.00',
                ]);

                if ($deal->products->isNotEmpty()) {
                    $equipment = QuoteEquipment::create([
                        'tenant_id' => $deal->tenant_id,
                        'quote_id' => $quote->id,
                        'description' => 'Itens do negócio',
                        'sort_order' => 1,
                    ]);

                    $sortOrder = 1;
                    foreach ($deal->products as $dealProduct) {
                        QuoteItem::create([
                            'tenant_id' => $deal->tenant_id,
                            'quote_id' => $quote->id,
                            'quote_equipment_id' => $equipment->id,
                            'type' => 'product',
                            'product_id' => $dealProduct->product_id,
                            'quantity' => $dealProduct->quantity,
                            'original_price' => $dealProduct->unit_price,
                            'unit_price' => $dealProduct->unit_price,
                            'sort_order' => $sortOrder++,
                        ]);
                    }

                    $quote->recalculateTotal();
                } else {
                    $quote->update([
                        'subtotal' => $deal->value ?? '0.00',
                        'total' => $deal->value ?? '0.00',
                    ]);
                }

                $deal->update(['quote_id' => $quote->id]);

                CrmActivity::logSystemEvent(
                    $deal->tenant_id,
                    $deal->customer_id,
                    "Orçamento #{$quote->quote_number} criado a partir do negócio: {$deal->title}",
                    $deal->id
                );

                return $quote->load('customer:id,name', 'equipments.items.product', 'equipments.items.service');
            });

            return ApiResponse::data(['quote' => $quote, 'message' => 'Orçamento criado com sucesso.'], 201);
        } catch (\Exception $e) {
            Log::error('Erro ao criar orçamento a partir do deal', ['deal_id' => $deal->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao criar orçamento. Tente novamente.', 500);
        }
    }
}
