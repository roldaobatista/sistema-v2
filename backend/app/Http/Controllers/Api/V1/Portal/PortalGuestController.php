<?php

namespace App\Http\Controllers\Api\V1\Portal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\ConsumeGuestLinkRequest;
use App\Http\Resources\QuoteResource;
use App\Http\Resources\WorkOrderResource;
use App\Models\AuditLog;
use App\Models\PortalGuestLink;
use App\Models\Quote;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\QuoteService;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\DB;

class PortalGuestController extends Controller
{
    public function __construct(private readonly QuoteService $quoteService) {}

    public function show(string $token)
    {
        $guestLink = PortalGuestLink::with('entity')->where('token', $token)->first();

        if (! $guestLink || ! $guestLink->isValid()) {
            return ApiResponse::message('Este link de acesso expirou ou é invalido.', 404);
        }

        $entity = $guestLink->entity;

        if ($entity instanceof Quote) {
            $entity->load([
                'seller:id,name',
                'customer:id,name',
                'equipments.equipment:id,brand,model,serial_number',
                'equipments.items.product:id,name',
                'equipments.items.service:id,name',
            ]);
            $data = new QuoteResource($entity);
        } elseif ($entity instanceof WorkOrder) {
            $entity->load(['customer:id,name', 'items', 'equipment', 'statusHistory.user:id,name']);
            $data = new WorkOrderResource($entity);
        } else {
            $data = null;
        }

        return ApiResponse::data([
            'type' => class_basename($guestLink->entity_type),
            'resource' => $data,
        ]);
    }

    public function consume(ConsumeGuestLinkRequest $request, string $token)
    {
        $guestLink = PortalGuestLink::with('entity')->where('token', $token)->first();

        if (! $guestLink || ! $guestLink->isValid()) {
            return ApiResponse::message('Este link de acesso expirou ou é invalido.', 404);
        }

        $validated = $request->validated();

        $entity = $guestLink->entity;

        if ($entity instanceof Quote) {
            $status = $entity->status instanceof \BackedEnum ? $entity->status->value : (string) $entity->status;

            if ($status !== Quote::STATUS_SENT) {
                return ApiResponse::message('Este orcamento nao pode mais ser alterado.', 422);
            }

            if ($entity->valid_until && $entity->valid_until < now()) {
                return ApiResponse::message('Este orcamento esta expirado.', 422);
            }

            $actor = $entity->seller ?: User::where('tenant_id', $entity->tenant_id)->orderBy('id')->first();
            $signerName = $validated['signer_name'] ?? 'Cliente (Link Convidado)';

            if ($validated['action'] === 'approve') {
                $this->quoteService->approveQuote(
                    $entity,
                    $actor,
                    [
                        'approval_channel' => 'guest_portal',
                        'approved_by_name' => $signerName,
                        'approval_notes' => $validated['comments'] ?? null,
                    ],
                    'Orçamento aprovado pelo cliente via Guest Link'
                );
            } elseif ($validated['action'] === 'reject') {
                DB::transaction(function () use ($entity, $validated, $signerName) {
                    $entity->update([
                        'status' => Quote::STATUS_REJECTED,
                        'rejected_at' => now(),
                        'rejection_reason' => $validated['comments'] ?? null,
                        'approval_channel' => 'guest_portal',
                        'approved_by_name' => $signerName,
                    ]);

                    AuditLog::log(
                        'status_changed',
                        'Orçamento rejeitado pelo cliente via Guest Link',
                        $entity
                    );
                });
            } else {
                return ApiResponse::message('Acao invalida.', 422);
            }

            $guestLink->consume();

            return ApiResponse::data(
                new QuoteResource($entity->fresh()),
                200,
                ['message' => 'Operacao concluida com sucesso.']
            );
        }

        // Add other entity specific actions here if needed later (e.g. WorkOrder signatures via Guest)

        $guestLink->consume();

        return ApiResponse::message('Acesso registrado com sucesso.', 200);
    }
}
