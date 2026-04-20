<?php

namespace App\Http\Controllers\Api\V1\Webhook;

use App\Models\CrmActivity;
use App\Models\CrmMessage;
use App\Models\Customer;
use App\Models\WhatsappMessageLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Webhook endpoint for WhatsApp providers (Evolution API, Z-API, Meta)
 * to report delivery status updates (sent, delivered, read, failed).
 */
class WhatsAppWebhookController extends Controller
{
    /**
     * Validate HMAC signature from the provider.
     * Returns false if the secret is configured but the signature is invalid.
     */
    private function validateSignature(Request $request): bool
    {
        $secret = config('services.whatsapp.webhook_secret');

        if (! $secret) {
            // Em produção, bloqueia sempre. Em dev/test, permite para facilitar integração inicial.
            if (app()->environment('production')) {
                Log::critical('WhatsApp webhook: webhook_secret não configurado! Defina WHATSAPP_WEBHOOK_SECRET no .env');

                return false;
            }

            return true;
        }

        $signature = $request->header('X-Hub-Signature-256')
            ?? $request->header('X-Signature')
            ?? null;

        if (! $signature) {
            Log::warning('WhatsApp webhook: header de assinatura ausente');

            return false;
        }

        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $signature);
    }

    /**
     * Legacy consolidated webhook endpoint.
     * POST /api/webhooks/whatsapp
     *
     * Accepts Evolution API bulk format: { event, data: [{...}, ...] }
     * Dispatches each item to processStatusPayload() or processMessagePayload()
     * based on event type. Maintained for backwards compatibility.
     */
    public function handle(Request $request): JsonResponse
    {
        if (! $this->validateSignature($request)) {
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $event = (string) ($request->input('event') ?? '');
        $dataItems = $request->input('data', []);

        if (! is_array($dataItems) || empty($dataItems)) {
            return response()->json(['ok' => true, 'status' => 'ignored']);
        }

        // messages.update -> status update ; messages.upsert -> inbound message
        $isStatusEvent = str_contains(strtolower($event), 'update')
            || str_contains(strtolower($event), 'status');

        foreach ($dataItems as $item) {
            if (! is_array($item)) {
                continue;
            }

            // Flatten "update" sub-object into top-level for legacy status events
            if ($isStatusEvent && isset($item['update']) && is_array($item['update'])) {
                $item = array_merge($item, $item['update']);
            }

            // Preserve provider instance if present at envelope level
            if (! isset($item['instance']) && $request->has('instance')) {
                $item['instance'] = $request->input('instance');
            }

            if ($isStatusEvent) {
                $this->processStatusPayload($item);
            } else {
                $this->processMessagePayload($item);
            }
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Handle delivery status webhook from WhatsApp providers.
     * POST /api/v1/webhooks/whatsapp/status
     */
    public function handleStatus(Request $request): JsonResponse
    {
        if (! $this->validateSignature($request)) {
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $this->processStatusPayload($request->all());

        return response()->json(['ok' => true]);
    }

    /**
     * Process a single status payload (extracted for reuse by legacy handle()).
     *
     * @param  array<string, mixed>  $data
     */
    private function processStatusPayload(array $data): void
    {
        // Evolution API format
        $externalId = $data['key']['id']
            ?? $data['messageId']
            ?? $data['id']
            ?? null;

        $status = $data['status']
            ?? $data['event']
            ?? null;

        if (! $externalId || ! $status) {
            return;
        }

        // LEI 4 JUSTIFICATIVA: webhook autenticado por assinatura nao tem usuario/current_tenant_id;
        // external_id e unico globalmente e permite escapar apenas do tenant scope.
        $log = WhatsappMessageLog::withoutGlobalScope('tenant')
            ->where('external_id', $externalId)
            ->first();

        // LEI 4 JUSTIFICATIVA: mesmo callback pode atualizar CrmMessage sem log;
        // external_id e unico globalmente e channel restringe o tipo de mensagem.
        $crmMessage = CrmMessage::withoutGlobalScope('tenant')
            ->where('external_id', $externalId)
            ->where('channel', CrmMessage::CHANNEL_WHATSAPP)
            ->first();

        if (! $log && ! $crmMessage) {
            Log::debug("WhatsApp webhook: message not found for external_id={$externalId}");

            return;
        }

        $sentAt = $log instanceof WhatsappMessageLog ? $log->sent_at : now();

        $updates = match (strtolower($status)) {
            'sent', 'server_ack' => ['status' => 'sent', 'sent_at' => $sentAt ?? now()],
            'delivered', 'delivery_ack' => ['status' => 'delivered', 'delivered_at' => now()],
            'read', 'read_ack' => ['status' => 'read', 'read_at' => now()],
            'failed', 'error' => ['status' => 'failed', 'error_message' => $data['error']['message'] ?? $data['reason'] ?? 'Unknown error'],
            default => null,
        };

        if ($updates) {
            if ($log) {
                $log->update($updates);
            }

            if ($crmMessage) {
                if ($updates['status'] === 'sent') {
                    $crmMessage->markSent();
                } elseif ($updates['status'] === 'delivered') {
                    $crmMessage->markDelivered();
                } elseif ($updates['status'] === 'read') {
                    $crmMessage->markRead();
                } elseif ($updates['status'] === 'failed') {
                    $crmMessage->markFailed((string) $updates['error_message']);
                }
            }

            Log::info('WhatsApp delivery status updated', [
                'message_id' => $log?->id,
                'crm_message_id' => $crmMessage?->id,
                'external_id' => $externalId,
                'new_status' => $updates['status'],
            ]);
        }
    }

    /**
     * Handle incoming message webhook (for future use / auto-reply).
     * POST /api/v1/webhooks/whatsapp/messages
     */
    public function handleMessage(Request $request): JsonResponse
    {
        if (! $this->validateSignature($request)) {
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $this->processMessagePayload($request->all());

        return response()->json(['ok' => true]);
    }

    /**
     * Process a single inbound message payload (extracted for reuse by legacy handle()).
     *
     * @param  array<string, mixed>  $data
     */
    private function processMessagePayload(array $data): void
    {
        Log::info('WhatsApp incoming message webhook received', [
            'from' => $data['key']['remoteJid'] ?? $data['from'] ?? 'unknown',
            'type' => $data['messageType'] ?? $data['type'] ?? 'unknown',
        ]);

        $from = $data['key']['remoteJid'] ?? $data['from'] ?? null;
        $message = $data['message']['conversation']
            ?? $data['message']['extendedTextMessage']['text']
            ?? $data['body']
            ?? null;

        if (! $from || ! $message) {
            return;
        }

        $phone = preg_replace('/[^0-9]/', '', explode('@', $from)[0]);

        // Resolve tenant exclusivamente pela instância configurada (nunca aceitar do payload)
        $tenantId = null;
        $instance = $data['instance'] ?? $data['instanceName'] ?? null;
        if ($instance) {
            $tenantId = DB::table('whatsapp_configs')
                ->where('instance_name', $instance)
                ->value('tenant_id');
        }

        // Fallback para ambientes legados: se não houver config, buscar por cliente com match de phone
        if (! $tenantId) {
            $normalizedPhone = preg_replace('/[^0-9]/', '', $phone);
            $lastNineDigits = substr($normalizedPhone, -9);

            $candidate = Customer::withoutGlobalScope('tenant')
                ->where(function ($q) use ($normalizedPhone, $lastNineDigits) {
                    $q->where('phone', $normalizedPhone)
                        ->orWhere('phone2', $normalizedPhone)
                        ->orWhere('phone', 'LIKE', '%'.$lastNineDigits)
                        ->orWhere('phone2', 'LIKE', '%'.$lastNineDigits);
                })
                ->whereExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('crm_messages')
                        ->whereColumn('crm_messages.customer_id', 'customers.id')
                        ->where('crm_messages.direction', CrmMessage::DIRECTION_OUTBOUND)
                        ->where('crm_messages.channel', CrmMessage::CHANNEL_WHATSAPP);
                })
                ->first();

            if ($candidate) {
                $tenantId = $candidate->tenant_id;
            }
        }

        if (! $tenantId) {
            Log::warning('WhatsApp webhook: não foi possível determinar tenant_id, mensagem descartada', [
                'from' => $from,
                'instance' => $instance,
            ]);

            return;
        }

        // Setar contexto de tenant para que o BelongsToTenant scope funcione no create
        app()->instance('current_tenant_id', $tenantId);

        $externalId = $data['key']['id'] ?? null;

        WhatsappMessageLog::create([
            'tenant_id' => $tenantId,
            'direction' => 'inbound',
            'phone_from' => $phone,
            'phone_to' => null,
            'message' => $message,
            'message_type' => 'text',
            'status' => 'received',
            'external_id' => $externalId,
            'sent_at' => now(),
        ]);

        // Bridge to CRM: create CrmMessage if customer is found by phone (within resolved tenant)
        $normalizedPhone = preg_replace('/[^0-9]/', '', $phone);
        $lastNineDigits = substr($normalizedPhone, -9);

        $customer = Customer::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where(function ($q) use ($normalizedPhone, $lastNineDigits) {
                $q->where('phone', $normalizedPhone)
                    ->orWhere('phone2', $normalizedPhone)
                    ->orWhere('phone', 'LIKE', '%'.$lastNineDigits)
                    ->orWhere('phone2', 'LIKE', '%'.$lastNineDigits);
            })
            ->first();

        if ($customer) {
            // Carregar deal/user da última mensagem outbound para preservar contexto de conversa
            $lastOutbound = CrmMessage::withoutGlobalScope('tenant')
                ->where('tenant_id', $tenantId)
                ->where('customer_id', $customer->id)
                ->where('channel', CrmMessage::CHANNEL_WHATSAPP)
                ->where('direction', CrmMessage::DIRECTION_OUTBOUND)
                ->latest()
                ->first();

            $crmMessage = CrmMessage::create([
                'tenant_id' => $tenantId,
                'customer_id' => $customer->id,
                'deal_id' => $lastOutbound?->deal_id,
                'user_id' => $lastOutbound?->user_id,
                'channel' => CrmMessage::CHANNEL_WHATSAPP,
                'direction' => CrmMessage::DIRECTION_INBOUND,
                'status' => CrmMessage::STATUS_DELIVERED,
                'body' => $message,
                'from_address' => $phone,
                'external_id' => $externalId,
                'delivered_at' => now(),
            ]);

            // Timeline: registrar atividade no CRM
            if (class_exists(CrmActivity::class)) {
                CrmActivity::create([
                    'tenant_id' => $tenantId,
                    'customer_id' => $customer->id,
                    'deal_id' => $lastOutbound?->deal_id,
                    'user_id' => $lastOutbound?->user_id,
                    'type' => 'whatsapp',
                    'channel' => 'whatsapp',
                    'title' => 'Mensagem recebida via WhatsApp',
                    'description' => $message,
                    'is_automated' => true,
                    'completed_at' => now(),
                ]);
            }
        }
    }
}
