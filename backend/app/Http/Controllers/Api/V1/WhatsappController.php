<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Features\SaveWhatsappConfigRequest;
use App\Http\Requests\Features\SendWhatsappRequest;
use App\Http\Requests\Features\TestWhatsappRequest;
use App\Models\WhatsappConfig;
use App\Models\WhatsappMessageLog;
use App\Services\WhatsAppService;
use App\Support\ApiResponse;
use App\Support\SearchSanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class WhatsappController extends Controller
{
    private function tenantId(Request $request): int
    {
        $user = $request->user();

        return (int) ($user->current_tenant_id ?? $user->tenant_id);
    }

    public function getWhatsappConfig(Request $request): JsonResponse
    {
        $config = WhatsappConfig::where('tenant_id', $this->tenantId($request))->first();

        return ApiResponse::data($config);
    }

    public function saveWhatsappConfig(SaveWhatsappConfigRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['tenant_id'] = $this->tenantId($request);
        $config = WhatsappConfig::updateOrCreate(['tenant_id' => $data['tenant_id']], $data);

        return ApiResponse::data($config);
    }

    public function testWhatsapp(TestWhatsappRequest $request, WhatsAppService $service): JsonResponse
    {
        $phone = $request->validated()['phone'];
        $log = $service->sendText($this->tenantId($request), $phone, '✅ Teste de conexão WhatsApp — Kalibrium');

        return ApiResponse::data(['success' => $log?->status === 'sent', 'log' => $log]);
    }

    public function sendWhatsapp(SendWhatsappRequest $request, WhatsAppService $service): JsonResponse
    {
        $data = $request->validated();
        $log = $service->sendText($this->tenantId($request), $data['phone'], $data['message']);

        return ApiResponse::data($log);
    }

    public function whatsappLogs(Request $request): JsonResponse
    {
        try {
            $tid = $this->tenantId($request);

            if (! Schema::hasTable('whatsapp_message_logs')) {
                return ApiResponse::data([]);
            }

            $query = WhatsappMessageLog::where('tenant_id', $tid)->orderByDesc('created_at');

            if ($search = $request->input('search')) {
                $safe = SearchSanitizer::contains($search);
                $query->where(function ($q) use ($safe) {
                    $q->where('phone', 'like', $safe)
                        ->orWhere('content', 'like', $safe);
                });
            }

            if ($status = $request->input('status')) {
                $query->where('status', $status);
            }

            return ApiResponse::data($query->limit(200)->get());
        } catch (\Throwable $e) {
            Log::error('whatsappLogs failed', ['error' => $e->getMessage()]);

            return ApiResponse::data([]);
        }
    }
}
