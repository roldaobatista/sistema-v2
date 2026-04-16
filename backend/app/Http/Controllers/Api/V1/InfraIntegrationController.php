<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Infra\CreateApiKeyRequest;
use App\Http\Requests\Infra\StoreWebhookConfigRequest;
use App\Http\Requests\Infra\UpdateWebhookConfigRequest;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class InfraIntegrationController extends Controller
{
    // ─── #49 Webhook Configurável ───────────────────────────────

    public function webhookConfigs(Request $request): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;

        return ApiResponse::data(DB::table('webhook_configs')->where('tenant_id', $tenantId)->get());
    }

    public function storeWebhook(StoreWebhookConfigRequest $request): JsonResponse
    {
        $data = $request->validated();

        $data['tenant_id'] = $request->user()->current_tenant_id;
        $data['secret'] = $data['secret'] ?? Str::random(32);
        $data['events'] = json_encode($data['events']);
        $data['headers'] = json_encode($data['headers'] ?? []);

        $id = DB::table('webhook_configs')->insertGetId(array_merge($data, [
            'created_at' => now(), 'updated_at' => now(),
        ]));

        return ApiResponse::data(['id' => $id, 'secret' => $data['secret']], 201);
    }

    public function updateWebhook(UpdateWebhookConfigRequest $request, int $id): JsonResponse
    {
        $data = $request->validated();

        if (isset($data['events'])) {
            $data['events'] = json_encode($data['events']);
        }
        if (isset($data['headers'])) {
            $data['headers'] = json_encode($data['headers']);
        }

        DB::table('webhook_configs')
            ->where('id', $id)
            ->where('tenant_id', $request->user()->current_tenant_id)
            ->update(array_merge($data, ['updated_at' => now()]));

        return ApiResponse::message('Atualizado com sucesso.');
    }

    public function deleteWebhook(Request $request, int $id): JsonResponse
    {
        DB::table('webhook_configs')
            ->where('id', $id)->where('tenant_id', $request->user()->current_tenant_id)->delete();

        return ApiResponse::message('Excluído com sucesso.');
    }

    public function testWebhook(Request $request, int $id): JsonResponse
    {
        $webhook = DB::table('webhook_configs')
            ->where('id', $id)->where('tenant_id', $request->user()->current_tenant_id)->first();

        if (! $webhook) {
            return ApiResponse::message('Registro não encontrado.', 404);
        }

        $payload = ['event' => 'test', 'timestamp' => now()->toIso8601String(), 'data' => ['message' => 'Test webhook']];
        $signature = hash_hmac('sha256', json_encode($payload), $webhook->secret);

        try {
            $response = Http::timeout(10)
                ->withHeaders(array_merge(json_decode($webhook->headers ?? '{}', true), [
                    'X-Webhook-Signature' => $signature,
                    'X-Webhook-Event' => 'test',
                ]))
                ->post($webhook->url, $payload);

            $success = $response->successful();

            DB::table('webhook_logs')->insert([
                'webhook_config_id' => $id,
                'event' => 'test',
                'payload' => json_encode($payload),
                'response_code' => $response->status(),
                'response_body' => substr($response->body(), 0, 1000),
                'success' => $success,
                'created_at' => now(),
            ]);

            return ApiResponse::data([
                'success' => $success,
                'status_code' => $response->status(),
            ]);
        } catch (\Throwable $e) {
            Log::error($e->getMessage(), ['exception' => $e]);

            return ApiResponse::message('Erro interno do servidor.', 500);
        }
    }

    public function webhookLogs(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;

        // Verify webhook belongs to tenant before showing logs
        $exists = DB::table('webhook_configs')
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->exists();

        if (! $exists) {
            return ApiResponse::message('Webhook não encontrado.', 404);
        }

        $logs = DB::table('webhook_logs')
            ->where('webhook_config_id', $id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return ApiResponse::data($logs);
    }

    // ─── #50 API Pública com Documentação Swagger ──────────────

    public function apiKeys(Request $request): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;
        $keys = DB::table('api_keys')
            ->where('tenant_id', $tenantId)
            ->get(['id', 'name', 'prefix', 'permissions', 'last_used_at', 'expires_at', 'is_active', 'created_at']);

        return ApiResponse::data($keys);
    }

    public function createApiKey(CreateApiKeyRequest $request): JsonResponse
    {
        $data = $request->validated();

        $tenantId = $request->user()->current_tenant_id;
        $key = Str::random(48);
        $prefix = substr($key, 0, 8);

        $id = DB::table('api_keys')->insertGetId([
            'tenant_id' => $tenantId,
            'name' => $data['name'],
            'key_hash' => hash('sha256', $key),
            'prefix' => $prefix,
            'permissions' => json_encode($data['permissions']),
            'expires_at' => $data['expires_at'] ?? null,
            'is_active' => true,
            'created_by' => $request->user()->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return ApiResponse::data([
            'id' => $id,
            'api_key' => "klb_{$key}",
            'prefix' => "klb_{$prefix}...",
        ], 201, ['message' => 'Salve esta chave; ela não será exibida novamente.']);
    }

    public function revokeApiKey(Request $request, int $id): JsonResponse
    {
        DB::table('api_keys')
            ->where('id', $id)->where('tenant_id', $request->user()->current_tenant_id)
            ->update(['is_active' => false, 'revoked_at' => now(), 'updated_at' => now()]);

        return ApiResponse::message('Chave de API revogada.');
    }

    public function swaggerSpec(Request $request): JsonResponse
    {
        $spec = [
            'openapi' => '3.0.3',
            'info' => [
                'title' => 'KALIBRIUM ERP Public API',
                'version' => '1.0.0',
                'description' => 'API pública para integração com sistemas externos',
            ],
            'servers' => [
                ['url' => config('app.url').'/api/v1/public', 'description' => 'Production'],
            ],
            'security' => [
                ['ApiKeyAuth' => []],
            ],
            'components' => [
                'securitySchemes' => [
                    'ApiKeyAuth' => ['type' => 'apiKey', 'in' => 'header', 'name' => 'X-API-Key'],
                ],
            ],
            'paths' => [
                '/work-orders' => [
                    'get' => [
                        'summary' => 'List work orders',
                        'tags' => ['Work Orders'],
                        'parameters' => [
                            ['name' => 'status', 'in' => 'query', 'schema' => ['type' => 'string']],
                            ['name' => 'from', 'in' => 'query', 'schema' => ['type' => 'string', 'format' => 'date']],
                            ['name' => 'to', 'in' => 'query', 'schema' => ['type' => 'string', 'format' => 'date']],
                        ],
                        'responses' => ['200' => ['description' => 'List of work orders']],
                    ],
                ],
                '/customers' => [
                    'get' => [
                        'summary' => 'List customers',
                        'tags' => ['Customers'],
                        'responses' => ['200' => ['description' => 'List of customers']],
                    ],
                ],
                '/stock' => [
                    'get' => [
                        'summary' => 'List stock levels',
                        'tags' => ['Stock'],
                        'responses' => ['200' => ['description' => 'Stock levels']],
                    ],
                ],
            ],
        ];

        return ApiResponse::data($spec);
    }
}
