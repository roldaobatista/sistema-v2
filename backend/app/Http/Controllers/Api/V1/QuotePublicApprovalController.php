<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Quote\ApproveQuotePublicRequest;
use App\Http\Requests\Quote\RejectQuotePublicRequest;
use App\Models\AuditLog;
use App\Models\Quote;
use App\Services\QuoteService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class QuotePublicApprovalController extends Controller
{
    private const PUBLIC_QUOTE_RELATIONS = [
        'customer:id,name',
        'tenant:id,name',
        'equipments.equipment',
        'equipments.items.product:id,name',
        'equipments.items.service:id,name',
    ];

    public function __construct(
        private readonly QuoteService $quoteService,
    ) {}

    /**
     * Public route (no auth) - validates magic token and shows quote for approval (4.33).
     */
    public function show(string $magicToken)
    {
        $quote = Quote::where('magic_token', $magicToken)
            ->where('status', Quote::STATUS_SENT)
            ->with(self::PUBLIC_QUOTE_RELATIONS)
            ->first();

        if (! $quote) {
            return ApiResponse::message('Proposta nao encontrada ou ja processada.', 404);
        }

        if ($expiredResponse = $this->ensureQuoteNotExpired($quote)) {
            return $expiredResponse;
        }

        $this->quoteService->trackClientView($quote);
        AuditLog::log('public_viewed', "Orçamento {$quote->quote_number} visualizado pelo cliente via link público", $quote);
        $quote->loadMissing(self::PUBLIC_QUOTE_RELATIONS);

        $items = $quote->equipments
            ->flatMap(fn ($equipment) => $equipment->relationLoaded('items') ? ($equipment->items ?? collect()) : collect())
            ->map(fn ($item): array => [
                'id' => $item->id,
                'description' => $item->custom_description
                    ?: $item->product?->name
                    ?: $item->service?->name
                    ?: 'Item do orcamento',
                'quantity' => (float) $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'subtotal' => (float) $item->subtotal,
            ])
            ->values();

        return ApiResponse::data([
            'id' => $quote->id,
            'quote_number' => $quote->quote_number,
            'reference' => $quote->reference ?? $quote->quote_number ?? "ORC-{$quote->id}",
            'total' => (float) $quote->total,
            'valid_until' => $quote->valid_until?->toDateString(),
            'items' => $items,
            'customer_name' => $quote->customer?->name ?? 'Cliente',
            'company_name' => $quote->tenant?->name ?? '',
            'pdf_url' => $quote->pdf_url,
        ]);
    }

    /**
     * Public route - client approves the quote via magic link.
     */
    public function approve(string $magicToken, ApproveQuotePublicRequest $request)
    {
        $quote = Quote::where('magic_token', $magicToken)
            ->where('status', Quote::STATUS_SENT)
            ->with(['customer:id,name', 'seller:id,name,email'])
            ->first();

        if (! $quote) {
            return ApiResponse::message('Proposta não encontrada ou já processada.', 404);
        }

        if ($expiredResponse = $this->ensureQuoteNotExpired($quote)) {
            return $expiredResponse;
        }

        try {
            $quote = $this->quoteService->publicApprove($quote, [
                'client_ip_approval' => $request->ip(),
                'term_accepted_at' => now(),
                'approval_channel' => 'magic_link',
                'approved_by_name' => $quote->customer?->name ?? 'Cliente',
            ]);
        } catch (\DomainException $e) {
            return ApiResponse::message($e->getMessage(), 422);
        } catch (\Throwable $e) {
            Log::error('QuotePublicApproval approve failed', [
                'magic_token' => $magicToken,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::message('Erro ao aprovar proposta.', 500);
        }

        return ApiResponse::data([
            'approved_at' => $quote->approved_at?->toISOString(),
        ], 200, ['message' => 'Proposta aprovada com sucesso!']);
    }

    /**
     * Public route - client rejects the quote via magic link.
     */
    public function reject(string $magicToken, RejectQuotePublicRequest $request)
    {
        $quote = Quote::where('magic_token', $magicToken)
            ->where('status', Quote::STATUS_SENT)
            ->with(['customer:id,name', 'seller:id,name,email'])
            ->first();

        if (! $quote) {
            return ApiResponse::message('Proposta não encontrada ou já processada.', 404);
        }

        if ($expiredResponse = $this->ensureQuoteNotExpired($quote)) {
            return $expiredResponse;
        }

        try {
            $reason = $request->validated('rejection_reason');
            $reasonText = $reason ? "{$reason} (via Link Público)" : 'Rejeitado via Link Público';

            $quote = $this->quoteService->rejectQuote($quote, $reasonText);

            // Register additional context for audit if needed
            AuditLog::log('status_changed', "Orçamento {$quote->quote_number} rejeitado pelo cliente via link público", $quote);
        } catch (\DomainException $e) {
            return ApiResponse::message($e->getMessage(), 422);
        } catch (\Throwable $e) {
            Log::error('QuotePublicApproval reject failed', [
                'magic_token' => $magicToken,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::message('Erro ao rejeitar proposta.', 500);
        }

        return ApiResponse::data([
            'rejected_at' => $quote->rejected_at?->toISOString(),
        ], 200, ['message' => 'Proposta rejeitada com sucesso!']);
    }

    private function ensureQuoteNotExpired(Quote $quote): ?JsonResponse
    {
        if (! $quote->isExpired()) {
            return null;
        }

        if (($quote->status instanceof \BackedEnum ? $quote->status->value : $quote->status) !== Quote::STATUS_EXPIRED) {
            $quote->forceFill(['status' => Quote::STATUS_EXPIRED])->save();
        }

        return ApiResponse::message('Proposta expirada.', 422);
    }
}
