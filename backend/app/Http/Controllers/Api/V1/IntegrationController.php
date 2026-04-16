<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Integration\CalculateShippingRequest;
use App\Http\Requests\Integration\EmailPluginWebhookRequest;
use App\Http\Requests\Integration\PowerBiDataExportRequest;
use App\Http\Requests\Integration\RequestPartnerIntegrationRequest;
use App\Http\Requests\Integration\StoreNotificationChannelRequest;
use App\Http\Requests\Integration\StoreWebhookRequest;
use App\Http\Requests\Integration\TriggerErpSyncRequest;
use App\Http\Requests\Integration\UpdateMarketingConfigRequest;
use App\Http\Requests\Integration\UpdateSsoConfigRequest;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class IntegrationController extends Controller
{
    private function tenantId(): int
    {
        $user = auth()->user();

        return (int) ($user->current_tenant_id ?? $user->tenant_id);
    }

    // ═══════════════════════════════════════════════════════════════════
    // 1. ZAPIER/WEBHOOKS
    // ═══════════════════════════════════════════════════════════════════

    public function webhooks(): JsonResponse
    {
        $data = DB::table('webhooks')
            ->where('tenant_id', $this->tenantId())
            ->orderBy('name')
            ->get();

        return ApiResponse::data($data);
    }

    public function storeWebhook(StoreWebhookRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            DB::beginTransaction();

            $legacyEvent = $validated['event'];
            $runtimeEvent = $this->normalizeWebhookEvent($legacyEvent);

            $id = DB::table('webhooks')->insertGetId([
                'tenant_id' => $this->tenantId(),
                'name' => $validated['name'] ?? "Webhook {$legacyEvent}",
                'url' => $validated['url'],
                'event' => $legacyEvent,
                'events' => json_encode([$runtimeEvent], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'secret' => $validated['secret'] ?? Str::random(32),
                'is_active' => $validated['is_active'] ?? true,
                'failure_count' => 0,
                'last_triggered_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::commit();

            return ApiResponse::message('Webhook registrado', 201, ['id' => $id]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Webhook creation failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao registrar webhook', 500);
        }
    }

    public function deleteWebhook(int $id): JsonResponse
    {
        try {
            DB::table('webhooks')
                ->where('id', $id)
                ->where('tenant_id', $this->tenantId())
                ->delete();

            return ApiResponse::message('Webhook removido');
        } catch (\Exception $e) {
            Log::error('Webhook deletion failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao remover webhook', 500);
        }
    }

    private function normalizeWebhookEvent(string $event): string
    {
        return match ($event) {
            'os.created' => 'work_order.created',
            'os.completed' => 'work_order.completed',
            'os.cancelled' => 'work_order.cancelled',
            default => $event,
        };
    }

    // ═══════════════════════════════════════════════════════════════════
    // 2. ERP SYNC (ContaAzul/Omie)
    // ═══════════════════════════════════════════════════════════════════

    public function erpSyncStatus(): JsonResponse
    {
        $syncs = DB::table('erp_sync_logs')
            ->where('tenant_id', $this->tenantId())
            ->orderByDesc('synced_at')
            ->limit(20)
            ->get();

        return ApiResponse::data($syncs);
    }

    public function triggerErpSync(TriggerErpSyncRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $id = DB::table('erp_sync_logs')->insertGetId([
                'tenant_id' => $this->tenantId(),
                'provider' => $validated['provider'],
                'modules' => json_encode($validated['modules']),
                'status' => 'queued',
                'synced_at' => now(),
                'created_by' => auth()->id(),
            ]);

            return ApiResponse::message('Sincronização adicionada à fila', 201, ['id' => $id]);
        } catch (\Exception $e) {
            Log::error('ERP sync trigger failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao iniciar sincronização', 500);
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // 3. PARTNER MARKETPLACE
    // ═══════════════════════════════════════════════════════════════════

    public function marketplace(): JsonResponse
    {
        $partners = DB::table('marketplace_partners')
            ->where('is_active', true)
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        return ApiResponse::data($partners);
    }

    public function requestPartnerIntegration(RequestPartnerIntegrationRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $id = DB::table('marketplace_requests')->insertGetId([
                'tenant_id' => $this->tenantId(),
                'partner_id' => $validated['partner_id'],
                'notes' => $validated['notes'] ?? null,
                'status' => 'pending',
                'created_by' => auth()->id(),
                'created_at' => now(),
            ]);

            return ApiResponse::message('Solicitação enviada ao parceiro', 201, ['id' => $id]);
        } catch (\Exception $e) {
            Log::error('Partner integration request failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao solicitar integração', 500);
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // 4. SSO (Google/Microsoft)
    // ═══════════════════════════════════════════════════════════════════

    public function ssoConfig(): JsonResponse
    {
        $config = DB::table('sso_configurations')
            ->where('tenant_id', $this->tenantId())
            ->get();

        return ApiResponse::data($config);
    }

    public function updateSsoConfig(UpdateSsoConfigRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            DB::table('sso_configurations')->updateOrInsert(
                ['tenant_id' => $this->tenantId(), 'provider' => $validated['provider']],
                [
                    'client_id' => encrypt($validated['client_id']),
                    'client_secret' => encrypt($validated['client_secret']),
                    'tenant_domain' => $validated['tenant_domain'] ?? null,
                    'is_active' => $validated['is_active'] ?? true,
                    'updated_at' => now(),
                ]
            );

            return ApiResponse::message('SSO configurado com sucesso');
        } catch (\Exception $e) {
            Log::error('SSO config update failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao configurar SSO', 500);
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // 5. SLACK/TEAMS NOTIFICATIONS
    // ═══════════════════════════════════════════════════════════════════

    public function slackTeamsConfig(): JsonResponse
    {
        $config = DB::table('notification_channels')
            ->where('tenant_id', $this->tenantId())
            ->whereIn('type', ['slack', 'teams'])
            ->get();

        return ApiResponse::data($config);
    }

    public function storeNotificationChannel(StoreNotificationChannelRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            DB::beginTransaction();

            $id = DB::table('notification_channels')->insertGetId([
                'tenant_id' => $this->tenantId(),
                'type' => $validated['type'],
                'webhook_url' => $validated['webhook_url'],
                'channel_name' => $validated['channel_name'] ?? null,
                'events' => json_encode($validated['events']),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::commit();

            return ApiResponse::message('Canal configurado', 201, ['id' => $id]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Notification channel creation failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao configurar canal', 500);
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // 6. SHIPPING CALCULATOR (Correios/Transportadoras)
    // ═══════════════════════════════════════════════════════════════════

    public function calculateShipping(CalculateShippingRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Simulated shipping quotes
        $weight = $validated['weight_kg'];
        $quotes = [
            ['carrier' => 'Correios PAC', 'price' => round(15 + ($weight * 3.5), 2), 'days' => rand(5, 10), 'type' => 'economic'],
            ['carrier' => 'Correios SEDEX', 'price' => round(25 + ($weight * 6.0), 2), 'days' => rand(2, 5), 'type' => 'express'],
            ['carrier' => 'Transportadora A', 'price' => round(20 + ($weight * 4.0), 2), 'days' => rand(3, 7), 'type' => 'standard'],
        ];

        return ApiResponse::data($quotes);
    }

    // ═══════════════════════════════════════════════════════════════════
    // 7. MARKETING TOOLS (RD Station)
    // ═══════════════════════════════════════════════════════════════════

    public function marketingIntegrationConfig(): JsonResponse
    {
        $config = DB::table('marketing_integrations')
            ->where('tenant_id', $this->tenantId())
            ->first();

        return ApiResponse::data($config);
    }

    public function updateMarketingConfig(UpdateMarketingConfigRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            DB::table('marketing_integrations')->updateOrInsert(
                ['tenant_id' => $this->tenantId()],
                [
                    'provider' => $validated['provider'],
                    'api_key' => encrypt($validated['api_key']),
                    'sync_contacts' => $validated['sync_contacts'] ?? true,
                    'sync_events' => $validated['sync_events'] ?? false,
                    'updated_at' => now(),
                ]
            );

            return ApiResponse::message('Integração de marketing configurada');
        } catch (\Exception $e) {
            Log::error('Marketing config update failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao configurar integração', 500);
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // 8. SWAGGER DOCS
    // ═══════════════════════════════════════════════════════════════════

    public function swaggerDoc(): JsonResponse
    {
        $spec = [
            'openapi' => '3.0.3',
            'info' => [
                'title' => 'Kalibrium ERP API',
                'version' => '1.0.0',
                'description' => 'API pública do sistema Kalibrium ERP para calibração e metrologia.',
            ],
            'servers' => [['url' => config('app.url').'/api/v1']],
            'paths' => [
                '/work-orders' => ['get' => ['summary' => 'Lista ordens de serviço', 'tags' => ['OS']]],
                '/customers' => ['get' => ['summary' => 'Lista clientes', 'tags' => ['Clientes']]],
                '/quotes' => ['get' => ['summary' => 'Lista orçamentos', 'tags' => ['Orçamentos']]],
                '/products' => ['get' => ['summary' => 'Lista produtos/serviços', 'tags' => ['Produtos']]],
                '/portal/dashboard/{customerId}' => ['get' => ['summary' => 'Dashboard do cliente', 'tags' => ['Portal']]],
            ],
        ];

        return ApiResponse::data($spec);
    }

    // ═══════════════════════════════════════════════════════════════════
    // 9. OUTLOOK/GMAIL PLUGINS
    // ═══════════════════════════════════════════════════════════════════

    public function emailPluginManifest(): JsonResponse
    {
        return ApiResponse::data([
            'name' => 'Kalibrium ERP Mail Plugin',
            'version' => '1.0.0',
            'description' => 'Integre seu email com o Kalibrium ERP para vincular emails a OS e clientes',
            'capabilities' => ['link_emails_to_os', 'create_ticket_from_email', 'view_customer_info', 'attach_to_crm'],
            'supported_providers' => ['outlook', 'gmail'],
            'webhook_url' => config('app.url').'/api/v1/integrations/email-plugin/webhook',
        ]);
    }

    public function emailPluginWebhook(EmailPluginWebhookRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $result = match ($validated['action']) {
                'lookup_customer' => DB::table('customers')
                    ->where('tenant_id', $this->tenantId())
                    ->where('email', $validated['email_from'])
                    ->first(['id', 'name', 'email', 'phone']),
                'create_ticket' => ['ticket_id' => DB::table('support_tickets')->insertGetId([
                    'tenant_id' => $this->tenantId(),
                    'source' => 'email_plugin',
                    'description' => $validated['email_subject'] ?? 'Ticket por email',
                    'status' => 'open',
                    'created_at' => now(),
                    'updated_at' => now(),
                ])],
                default => ['status' => 'processed'],
            };

            return ApiResponse::data($result);
        } catch (\Exception $e) {
            Log::error('Email plugin webhook failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao processar webhook', 500);
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // 10. POWER BI CONNECTOR
    // ═══════════════════════════════════════════════════════════════════

    public function powerBiDataExport(PowerBiDataExportRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $tenantId = $this->tenantId();
        $table = match ($validated['dataset']) {
            'work_orders' => 'work_orders',
            'customers' => 'customers',
            'financials' => 'accounts_receivable',
            'products' => 'products',
            'certificates' => 'calibration_certificates',
            'nps' => 'nps_surveys',
        };

        $query = DB::table($table)->where('tenant_id', $tenantId);

        if (! empty($validated['date_from'])) {
            $query->where('created_at', '>=', $validated['date_from']);
        }
        if (! empty($validated['date_to'])) {
            $query->where('created_at', '<=', $validated['date_to']);
        }

        $perPage = min((int) $request->input('per_page', 50), 100);
        $data = $query->paginate($perPage);

        return ApiResponse::paginated($data, extra: [
            'dataset' => $validated['dataset'],
            'exported_at' => now()->toIso8601String(),
        ]);
    }
}
