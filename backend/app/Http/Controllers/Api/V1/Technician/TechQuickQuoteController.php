<?php

namespace App\Http\Controllers\Api\V1\Technician;

use App\Enums\QuoteStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Technician\StoreTechQuickQuoteRequest;
use App\Models\AuditLog;
use App\Models\Quote;
use App\Models\QuoteEquipment;
use App\Models\QuoteItem;
use App\Services\QuoteService;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TechQuickQuoteController extends Controller
{
    use ResolvesCurrentTenant;

    public function store(StoreTechQuickQuoteRequest $request): JsonResponse
    {
        $tenantId = $this->tenantId();
        $user = $request->user();

        $validated = $request->validated();

        try {
            $quote = DB::transaction(function () use ($validated, $tenantId, $user) {
                // Determine quote number
                $quoteNumber = Quote::nextNumber($tenantId);

                $quote = Quote::create([
                    'tenant_id' => $tenantId,
                    'customer_id' => $validated['customer_id'],
                    'seller_id' => $user->id,
                    'created_by' => $user->id,
                    'status' => QuoteStatus::INTERNALLY_APPROVED, // Auto-aprovado em campo
                    'quote_number' => $quoteNumber,
                    'revision' => 1,
                    'discount_percentage' => $validated['discount_percentage'] ?? 0,
                    'observations' => $validated['observations'] ?? null,
                    'internal_approved_by' => $user->id,
                    'internal_approved_at' => now(),
                    'source' => 'app_tecnico',
                    'validity_days' => 15,
                    'valid_until' => now()->addDays(15),
                ]);

                $equipment = QuoteEquipment::create([
                    'tenant_id' => $tenantId,
                    'quote_id' => $quote->id,
                    'equipment_id' => $validated['equipment_id'],
                    'description' => 'Orçamento Rápido de Campo',
                    'sort_order' => 0,
                ]);

                foreach ($validated['items'] as $index => $item) {
                    QuoteItem::create([
                        'tenant_id' => $tenantId,
                        'quote_equipment_id' => $equipment->id,
                        'type' => $item['type'],
                        'product_id' => $item['type'] === 'product' ? $item['product_id'] : null,
                        'service_id' => $item['type'] === 'service' ? $item['service_id'] : null,
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'original_price' => $item['unit_price'],
                        'discount_percentage' => 0,
                        'sort_order' => $index,
                    ]);
                }

                $quote->recalculateTotal();

                AuditLog::log('created', "Orçamento Rápido {$quote->quote_number} criado e pré-aprovado", $quote);

                return $quote;
            });

            if ($request->boolean('send_to_client')) {
                app(QuoteService::class)->sendQuote($quote);
                $quote->refresh();
            }

            return response()->json([
                'data' => [
                    'id' => $quote->id,
                    'quote_number' => $quote->quote_number,
                    'status' => $quote->status,
                ],
                'message' => 'Orçamento rápido criado e aprovado com sucesso.',
            ], 201);
        } catch (\Exception $e) {
            Log::error('Quick Quote create failed', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
            ]);

            return response()->json(['message' => 'Erro ao criar orçamento rápido'], 500);
        }
    }
}
