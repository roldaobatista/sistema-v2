<?php

namespace App\Http\Controllers\Api\V1\Portal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\OneClickApprovalRequest;
use App\Models\Quote;
use App\Services\QuoteService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class PortalQuickQuoteApprovalController extends Controller
{
    public function __construct(
        private readonly QuoteService $quoteService,
    ) {}

    public function approve(OneClickApprovalRequest $request, int $quoteId): JsonResponse
    {
        $tenantId = (int) ($request->user()?->current_tenant_id ?? $request->user()?->tenant_id);
        $validated = $request->validated();

        try {
            $quote = Quote::query()
                ->where('id', $quoteId)
                ->where('customer_id', $validated['customer_id'])
                ->where('tenant_id', $tenantId)
                ->first();

            if (! $quote) {
                return ApiResponse::message('Orçamento não encontrado', 404);
            }

            if (! $quote->matchesPublicAccessToken((string) $validated['approval_token'])) {
                return ApiResponse::message('Token inválido', 403);
            }

            if (($quote->status->value ?? $quote->status) === Quote::STATUS_APPROVED) {
                return ApiResponse::message('Orçamento já aprovado anteriormente');
            }

            if ($quote->isExpired() && ($quote->status->value ?? $quote->status) !== Quote::STATUS_EXPIRED) {
                $quote->update(['status' => Quote::STATUS_EXPIRED]);

                return ApiResponse::message('Orçamento expirado', 422);
            }

            $approvedQuote = $this->quoteService->publicApprove($quote, [
                'approval_channel' => 'portal_one_click',
                'approved_by_name' => $quote->customer?->name,
                'approval_notes' => 'Aprovação rápida via portal do cliente',
                'client_ip_approval' => $request->ip(),
                'term_accepted_at' => now(),
            ]);

            return ApiResponse::data($approvedQuote->fresh(), 200, [
                'message' => 'Orçamento aprovado com sucesso!',
            ]);
        } catch (\DomainException $e) {
            return ApiResponse::message($e->getMessage(), 422);
        } catch (\Throwable $e) {
            Log::error('Portal quick quote approval failed', [
                'error' => $e->getMessage(),
                'quote_id' => $quoteId,
                'tenant_id' => $tenantId,
            ]);

            return ApiResponse::message('Erro ao aprovar orçamento.', 500);
        }
    }
}
