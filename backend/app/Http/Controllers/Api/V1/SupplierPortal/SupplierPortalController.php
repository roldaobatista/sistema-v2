<?php

namespace App\Http\Controllers\Api\V1\SupplierPortal;

use App\Http\Controllers\Controller;
use App\Http\Requests\SupplierPortal\AnswerSupplierQuotationRequest;
use App\Models\PortalGuestLink;
use App\Models\PurchaseQuotation;
use App\Models\PurchaseQuotationItem;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupplierPortalController extends Controller
{
    /**
     * Exibe os detalhes de uma cotação de compra para o portal do fornecedor
     */
    public function showQuotation(string $token): JsonResponse
    {
        $guestLink = PortalGuestLink::with('entity')->where('token', $token)->first();

        if (! $guestLink || ! $guestLink->isValid()) {
            return ApiResponse::message('Este link de acesso expirou ou é invalido.', 404);
        }

        $entity = $guestLink->entity;

        if (! $entity instanceof PurchaseQuotation) {
            return ApiResponse::message('Tipo de entidade invalida para o portal do fornecedor.', 422);
        }

        $entity->load(['supplier:id,business_name,document_number', 'items.product:id,name']);

        return ApiResponse::data([
            'type' => 'PurchaseQuotation',
            'resource' => $entity,
            'expires_at' => $guestLink->expires_at,
        ]);
    }

    /**
     * Permite que o fornecedor responda à cotação (aprovar/rejeitar valores)
     */
    public function answerQuotation(AnswerSupplierQuotationRequest $request, string $token): JsonResponse
    {
        $guestLink = PortalGuestLink::with('entity')->where('token', $token)->first();

        if (! $guestLink || ! $guestLink->isValid()) {
            return ApiResponse::message('Este link de acesso expirou ou é invalido.', 404);
        }

        $entity = $guestLink->entity;

        if (! $entity instanceof PurchaseQuotation) {
            return ApiResponse::message('Tipo de entidade invalida para o portal do fornecedor.', 422);
        }

        if ($entity->status !== 'pending') {
            return ApiResponse::message('Esta cotacao não pode mais ser respondida.', 422);
        }

        if ($entity->valid_until && $entity->valid_until < now()) {
            return ApiResponse::message('Esta cotacao esta expirada.', 422);
        }

        $validated = $request->validated();
        $this->ensureItemsBelongToQuotation($entity, $validated);

        DB::transaction(function () use ($entity, $validated, $guestLink) {
            if ($validated['action'] === 'reject') {
                $entity->update([
                    'status' => 'rejected',
                    'notes' => $validated['notes'] ?? null,
                ]);
            } else {
                // Submit prices
                foreach ($validated['items'] as $itemData) {
                    $item = $entity->items()->where('id', $itemData['id'])->first();
                    if ($item instanceof PurchaseQuotationItem) {
                        $item->update([
                            'unit_price' => $itemData['unit_price'],
                            'total' => (float) $item->quantity * (float) $itemData['unit_price'],
                        ]);
                    }
                }

                $entity->update([
                    'status' => 'answered',
                    'notes' => $validated['notes'] ?? null,
                    'total_amount' => $entity->items()->sum('total'),
                ]);
            }

            $guestLink->consume();
        });

        return ApiResponse::data($entity->fresh(['items']), 200, ['message' => 'Cotação respondida com sucesso.']);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function ensureItemsBelongToQuotation(PurchaseQuotation $quotation, array $validated): void
    {
        if (($validated['action'] ?? null) !== 'submit') {
            return;
        }

        /** @var array<int, array{id: mixed}> $submittedItems */
        $submittedItems = is_array($validated['items'] ?? null) ? $validated['items'] : [];

        $submittedIds = collect($submittedItems)
            ->pluck('id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values();

        $allowedIds = $quotation->items()
            ->whereIn('id', $submittedIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();

        if ($submittedIds->count() !== $allowedIds->count()) {
            throw ValidationException::withMessages([
                'items.0.id' => 'Todos os itens enviados precisam pertencer à cotação informada.',
            ]);
        }
    }
}
