<?php

namespace App\Services\Fiscal;

use App\Enums\FiscalNoteStatus;
use App\Events\FiscalNoteAuthorized;
use App\Models\FiscalEvent;
use App\Models\FiscalNote;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Processa o callback (webhook) da API externa quando a SEFAZ retorna de forma assíncrona.
 * Atualiza o status da nota no banco e dispara eventos para liberar OS / envio de certificado.
 */
class FiscalWebhookCallbackService
{
    public function __construct(
        private FiscalWebhookService $outgoingWebhooks,
    ) {}

    /**
     * Processa o payload do webhook e atualiza a nota fiscal.
     * Payload esperado (exemplos comuns de APIs):
     * - ref ou reference ou protocolo: identificador da emissão
     * - status: autorizado|autorizada|rejeitado|rejeitada|cancelado|processando
     * - chave_nfe ou chave: chave de acesso (44 dígitos)
     * - numero, serie, protocolo, caminho_danfe, caminho_xml_nota_fiscal, mensagem (erro)
     *
     * @return array{processed: bool, note_id: int|null, message: string}
     */
    public function process(array $payload): array
    {
        $reference = $payload['ref'] ?? $payload['reference'] ?? $payload['protocolo'] ?? null;
        if (empty($reference)) {
            Log::warning('FiscalWebhookCallback: missing ref/reference/protocolo', ['payload_keys' => array_keys($payload)]);

            return ['processed' => false, 'note_id' => null, 'message' => 'Referência ausente'];
        }

        // Webhook não tem contexto de tenant (sem auth) → BelongsToTenant scope bloquearia a busca.
        // Buscar sem scope de tenant por reference/protocol (únicos por emissão).
        $note = FiscalNote::withoutGlobalScope('tenant')
            ->where('reference', $reference)->first();
        if (! $note) {
            $note = FiscalNote::withoutGlobalScope('tenant')
                ->where('protocol_number', $reference)->first();
        }
        if (! $note) {
            Log::warning('FiscalWebhookCallback: note not found', ['reference' => $reference]);

            return ['processed' => false, 'note_id' => null, 'message' => 'Nota não encontrada'];
        }

        // Setar contexto de tenant para que queries/creates subsequentes funcionem
        app()->instance('current_tenant_id', $note->tenant_id);

        $statusRaw = $payload['status'] ?? $payload['status_sefaz'] ?? '';
        $newStatus = $this->normalizeStatus($statusRaw);
        $oldStatus = $note->status instanceof FiscalNoteStatus
            ? $note->status->value
            : (string) $note->status;

        if ($newStatus === null) {
            Log::info('FiscalWebhookCallback: status ignorado', ['reference' => $reference, 'status_raw' => $statusRaw]);

            return ['processed' => true, 'note_id' => $note->id, 'message' => 'Status não mapeado'];
        }

        try {
            DB::beginTransaction();

            $updateData = [
                'status' => $newStatus,
                'raw_response' => array_merge($note->raw_response ?? [], ['webhook' => $payload]),
            ];

            if (! empty($payload['chave_nfe'] ?? $payload['chave'] ?? null)) {
                $updateData['access_key'] = $payload['chave_nfe'] ?? $payload['chave'];
            }
            if (isset($payload['numero'])) {
                $updateData['number'] = (string) $payload['numero'];
            }
            if (isset($payload['serie'])) {
                $updateData['series'] = (string) $payload['serie'];
            }
            if (! empty($payload['protocolo'])) {
                $updateData['protocol_number'] = $payload['protocolo'];
            }
            if (! empty($payload['caminho_danfe'] ?? $payload['pdf_url'] ?? null)) {
                $updateData['pdf_url'] = $payload['caminho_danfe'] ?? $payload['pdf_url'];
            }
            if (! empty($payload['caminho_xml_nota_fiscal'] ?? $payload['xml_url'] ?? null)) {
                $updateData['xml_url'] = $payload['caminho_xml_nota_fiscal'] ?? $payload['xml_url'];
            }
            if ($newStatus === FiscalNote::STATUS_AUTHORIZED) {
                $updateData['issued_at'] = $note->issued_at ?? now();
                $updateData['contingency_mode'] = false;
                $updateData['error_message'] = null;
            }
            if (in_array($newStatus, [FiscalNote::STATUS_REJECTED, FiscalNote::STATUS_CANCELLED])) {
                $updateData['error_message'] = $payload['mensagem'] ?? $payload['message'] ?? $payload['motivo'] ?? null;
                if ($newStatus === FiscalNote::STATUS_CANCELLED) {
                    $updateData['cancelled_at'] = now();
                }
            }

            $note->update($updateData);

            $this->logEvent($note, 'webhook_callback', $payload, $newStatus);

            if ($newStatus !== $oldStatus) {
                $this->outgoingWebhooks->dispatch($note, $newStatus === FiscalNote::STATUS_AUTHORIZED ? 'authorized' : ($newStatus === FiscalNote::STATUS_REJECTED ? 'rejected' : 'cancelled'));
            }

            if ($newStatus === FiscalNote::STATUS_AUTHORIZED) {
                FiscalNoteAuthorized::dispatch($note);
            }

            DB::commit();

            Log::info('FiscalWebhookCallback: note updated', [
                'note_id' => $note->id,
                'reference' => $reference,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
            ]);

            return [
                'processed' => true,
                'note_id' => $note->id,
                'message' => 'Nota atualizada',
                'status' => $newStatus,
            ];
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('FiscalWebhookCallback: exception', [
                'reference' => $reference,
                'error' => $e->getMessage(),
            ]);

            return [
                'processed' => false,
                'note_id' => $note->id,
                'message' => 'Erro interno ao processar o webhook fiscal.',
            ];
        }
    }

    private function normalizeStatus(string $status): ?string
    {
        $s = strtolower(trim($status));

        return match ($s) {
            'autorizado', 'autorizada', 'authorized' => FiscalNote::STATUS_AUTHORIZED,
            'rejeitado', 'rejeitada', 'rejected', 'erro_autorizacao' => FiscalNote::STATUS_REJECTED,
            'cancelado', 'cancelada', 'cancelled' => FiscalNote::STATUS_CANCELLED,
            'processando', 'processing', 'processando_autorizacao' => FiscalNote::STATUS_PROCESSING,
            'pendente', 'pending' => FiscalNote::STATUS_PENDING,
            default => null,
        };
    }

    private function logEvent(FiscalNote $note, string $eventType, array $payload, string $status): void
    {
        FiscalEvent::create([
            'fiscal_note_id' => $note->id,
            'tenant_id' => $note->tenant_id,
            'event_type' => $eventType,
            'protocol_number' => $payload['protocolo'] ?? null,
            'description' => "Webhook: status {$status}",
            'response_payload' => $payload,
            'status' => $status,
            'user_id' => null,
        ]);
    }
}
