<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\EmailWebhookRequest;
use App\Http\Requests\Crm\SendCrmMessageRequest;
use App\Http\Requests\Crm\StoreCrmMessageTemplateRequest;
use App\Http\Requests\Crm\UpdateCrmMessageTemplateRequest;
use App\Http\Requests\Crm\WhatsAppWebhookRequest;
use App\Models\CrmMessage;
use App\Models\CrmMessageTemplate;
use App\Models\Customer;
use App\Models\User;
use App\Services\MessagingService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class CrmMessageController extends Controller
{
    public function __construct(private MessagingService $messaging) {}

    // ─── Messages ───────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CrmMessage::class);

        try {
            $query = CrmMessage::with(['customer:id,name', 'deal:id,title', 'user:id,name']);

            if ($request->filled('customer_id')) {
                $query->where('customer_id', $request->customer_id);
            }
            if ($request->filled('deal_id')) {
                $query->where('deal_id', $request->deal_id);
            }
            if ($request->filled('channel')) {
                $query->byChannel($request->channel);
            }
            if ($request->filled('direction')) {
                $query->where('direction', $request->direction);
            }

            $messages = $query->orderByDesc('created_at')
                ->paginate(min((int) ($request->per_page ?? 30), 100));

            return ApiResponse::paginated($messages);
        } catch (\Exception $e) {
            Log::error('CrmMessage index failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao listar mensagens.', 500);
        }
    }

    public function send(SendCrmMessageRequest $request): JsonResponse
    {
        $this->authorize('create', CrmMessage::class);

        try {
            DB::beginTransaction();

            /** @var User $user */
            $user = $request->user();
            $tenantId = (int) ($user->current_tenant_id ?? $user->tenant_id);
            $data = $request->validated();

            $customer = Customer::findOrFail($data['customer_id']);

            if (! empty($data['template_id'])) {
                $template = CrmMessageTemplate::query()
                    ->whereKey($data['template_id'])
                    ->where('tenant_id', $tenantId)
                    ->where('channel', $data['channel'])
                    ->where('is_active', true)
                    ->first();

                if (! $template) {
                    DB::rollBack();

                    return ApiResponse::message('Template invalido para o canal informado.', 422, [
                        'errors' => [
                            'template_id' => ['Selecione um template ativo compativel com o canal informado.'],
                        ],
                    ]);
                }

                $message = $this->messaging->sendFromTemplate(
                    $template, $customer,
                    $data['variables'] ?? [],
                    $data['deal_id'] ?? null,
                    $user->id
                );
            } else {
                $message = match ($data['channel']) {
                    'whatsapp' => $this->messaging->sendWhatsApp(
                        $tenantId, $customer, $data['body'],
                        $data['deal_id'] ?? null, $user->id
                    ),
                    'email' => $this->messaging->sendEmail(
                        $tenantId, $customer,
                        $data['subject'] ?? '(Sem assunto)',
                        $data['body'],
                        $data['deal_id'] ?? null, $user->id
                    ),
                };
            }

            DB::commit();

            return ApiResponse::data($message->load(['customer:id,name']), 201);
        } catch (ValidationException $e) {
            DB::rollBack();

            return ApiResponse::message('Validação falhou.', 422, ['errors' => $e->errors()]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('CrmMessage send failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao enviar mensagem.', 500);
        }
    }

    // ─── Templates ──────────────────────────────────────

    public function templates(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CrmMessageTemplate::class);

        try {
            $query = CrmMessageTemplate::query();

            if (! $request->boolean('include_inactive')) {
                $query->active();
            }

            if ($request->filled('channel')) {
                $query->byChannel($request->channel);
            }

            return ApiResponse::paginated($query->orderBy('name')->paginate(15));
        } catch (\Exception $e) {
            Log::error('CrmMessage templates failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao listar templates.', 500);
        }
    }

    public function storeTemplate(StoreCrmMessageTemplateRequest $request): JsonResponse
    {
        $this->authorize('create', CrmMessageTemplate::class);

        try {
            DB::beginTransaction();

            $data = $request->validated();

            /** @var User $user */
            $user = $request->user();
            $data['tenant_id'] = (int) ($user->current_tenant_id ?? $user->tenant_id);

            $template = CrmMessageTemplate::create($data);

            DB::commit();

            return ApiResponse::data($template, 201);
        } catch (ValidationException $e) {
            DB::rollBack();

            return ApiResponse::message('Validação falhou.', 422, ['errors' => $e->errors()]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('CrmMessage storeTemplate failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao criar template.', 500);
        }
    }

    public function updateTemplate(UpdateCrmMessageTemplateRequest $request, CrmMessageTemplate $template): JsonResponse
    {
        $this->authorize('update', $template);

        try {
            DB::beginTransaction();

            $data = $request->validated();

            $template->update($data);

            DB::commit();

            return ApiResponse::data($template);
        } catch (ValidationException $e) {
            DB::rollBack();

            return ApiResponse::message('Validação falhou.', 422, ['errors' => $e->errors()]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('CrmMessage updateTemplate failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao atualizar template.', 500);
        }
    }

    public function destroyTemplate(CrmMessageTemplate $template): JsonResponse
    {
        $this->authorize('delete', $template);

        try {
            $template->delete();

            return ApiResponse::noContent();
        } catch (\Exception $e) {
            Log::error('CrmMessage destroyTemplate failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao excluir template.', 500);
        }
    }

    // ─── Webhooks ────────────────────────────────────────

    public function webhookWhatsApp(WhatsAppWebhookRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            if (empty($data)) {
                return ApiResponse::data(['status' => 'ignored', 'reason' => 'empty payload']);
            }
            $event = $data['event'] ?? null;

            // Resolver tenant via instance_name do payload (padrão Evolution API)
            $instance = $data['instance'] ?? $data['instanceName'] ?? null;
            $webhookTenantId = null;
            if ($instance) {
                $webhookTenantId = DB::table('whatsapp_configs')
                    ->where('instance_name', $instance)
                    ->value('tenant_id');
            }

            if ($event === 'messages.update') {
                $updates = $data['data'] ?? [];
                foreach ((array) $updates as $update) {
                    $externalId = $update['key']['id'] ?? null;
                    if (! $externalId) {
                        continue;
                    }

                    // LEI 4 JUSTIFICATIVA: webhook assinado não tem usuário/current_tenant_id;
                    // external_id é único globalmente e, quando possível, filtrado pelo tenant da instância.
                    $query = CrmMessage::withoutGlobalScope('tenant')
                        ->where('external_id', $externalId);
                    if ($webhookTenantId) {
                        $query->where('tenant_id', $webhookTenantId);
                    }
                    $message = $query->first();
                    if (! $message) {
                        continue;
                    }

                    $status = $update['update']['status'] ?? null;
                    match ($status) {
                        'DELIVERY_ACK' => $message->markDelivered(),
                        'READ', 'PLAYED' => $message->markRead(),
                        default => null,
                    };
                }
            }

            if ($event === 'messages.upsert') {
                $msgs = $data['data'] ?? [];
                foreach ((array) $msgs as $msg) {
                    $isFromMe = $msg['key']['fromMe'] ?? true;
                    if ($isFromMe) {
                        continue;
                    }

                    $phone = preg_replace('/\D/', '', $msg['key']['remoteJid'] ?? '');
                    $phone = preg_replace('/^55/', '', $phone);
                    $body = $msg['message']['conversation']
                        ?? $msg['message']['extendedTextMessage']['text']
                        ?? '[Mídia]';

                    // Escapar caracteres especiais do LIKE para evitar match indevido
                    $phoneSafe = str_replace(['%', '_'], ['\\%', '\\_'], $phone);

                    $tenantId = $webhookTenantId;
                    $customer = null;

                    // Se temos tenant via instance_name, buscar dentro do tenant
                    if ($tenantId) {
                        app()->instance('current_tenant_id', $tenantId);

                        $customer = Customer::where(function ($q) use ($phoneSafe) {
                            $q->where('phone', 'like', "%{$phoneSafe}")
                                ->orWhere('phone2', 'like', "%{$phoneSafe}");
                        })->first();
                    } else {
                        // LEI 4 JUSTIFICATIVA: fallback legado de webhook assinado sem instância;
                        // o tenant é derivado da última mensagem outbound, nunca do payload.
                        $lastOutbound = CrmMessage::withoutGlobalScope('tenant')
                            ->where('to_address', 'like', "%{$phoneSafe}")
                            ->where('direction', CrmMessage::DIRECTION_OUTBOUND)
                            ->latest()
                            ->first();

                        $tenantId = $lastOutbound?->tenant_id;

                        if ($tenantId) {
                            // LEI 4 JUSTIFICATIVA: tenant foi inferido de conversa outbound;
                            // a busca sem scope é restrita explicitamente ao tenant encontrado.
                            $customer = Customer::withoutGlobalScope('tenant')
                                ->where('tenant_id', $tenantId)
                                ->where(function ($q) use ($phoneSafe) {
                                    $q->where('phone', 'like', "%{$phoneSafe}")
                                        ->orWhere('phone2', 'like', "%{$phoneSafe}");
                                })->first();
                        }
                    }

                    if (! $tenantId || ! $customer) {
                        continue;
                    }

                    // LEI 4 JUSTIFICATIVA: contexto de tenant foi resolvido antes da criação;
                    // busca sem scope é limitada ao tenant/cliente/canal para reaproveitar deal/user.
                    $lastOutbound = CrmMessage::withoutGlobalScope('tenant')
                        ->where('tenant_id', $tenantId)
                        ->where('customer_id', $customer->id)
                        ->where('channel', CrmMessage::CHANNEL_WHATSAPP)
                        ->where('direction', CrmMessage::DIRECTION_OUTBOUND)
                        ->latest()
                        ->first();

                    // Garantir contexto de tenant para o recordInbound
                    app()->instance('current_tenant_id', $tenantId);

                    $this->messaging->recordInbound(
                        $customer->tenant_id,
                        $customer->id,
                        'whatsapp',
                        $body,
                        $phone,
                        $msg['key']['id'] ?? null,
                        null,
                        ['raw' => $msg],
                        $lastOutbound?->deal_id,
                        $lastOutbound?->user_id
                    );
                }
            }

            return ApiResponse::data(['status' => 'ok']);
        } catch (\Exception $e) {
            Log::error('CrmMessage webhookWhatsApp failed', ['error' => $e->getMessage()]);
            Log::error($e->getMessage(), ['exception' => $e]);

            return ApiResponse::message('Erro interno do servidor.', 500);
        }
    }

    public function webhookEmail(EmailWebhookRequest $request): JsonResponse
    {
        try {
            $events = $request->validated();
            if (empty($events)) {
                return ApiResponse::data(['status' => 'ignored', 'reason' => 'empty payload']);
            }

            foreach ((array) $events as $event) {
                $type = $event['type'] ?? $event['event'] ?? null;
                $messageId = $event['message_id'] ?? $event['sg_message_id'] ?? null;

                if (! $messageId) {
                    continue;
                }

                // LEI 4 JUSTIFICATIVA: webhook autenticado por assinatura nao tem usuario/current_tenant_id;
                // external_id e unico globalmente para localizar o evento sem aceitar tenant do payload.
                $message = CrmMessage::withoutGlobalScope('tenant')
                    ->where('external_id', $messageId)
                    ->where('channel', CrmMessage::CHANNEL_EMAIL)
                    ->first();
                if (! $message) {
                    continue;
                }

                match ($type) {
                    'delivered', 'email.delivered' => $message->markDelivered(),
                    'opened', 'email.opened' => $message->markRead(),
                    'bounced', 'email.bounced', 'failed' => $message->markFailed($event['reason'] ?? 'Bounce'),
                    default => null,
                };
            }

            return ApiResponse::data(['status' => 'ok']);
        } catch (\Exception $e) {
            Log::error('CrmMessage webhookEmail failed', ['error' => $e->getMessage()]);
            Log::error($e->getMessage(), ['exception' => $e]);

            return ApiResponse::message('Erro interno do servidor.', 500);
        }
    }
}
