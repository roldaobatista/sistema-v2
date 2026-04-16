<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fiscal\FiscalWebhookCallbackRequest;
use App\Services\Fiscal\FiscalWebhookCallbackService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * Recebe o callback (webhook) da API externa quando a SEFAZ retorna de forma assíncrona.
 * Rota pública (sem auth), protegida por secret no header/body.
 */
class FiscalWebhookCallbackController extends Controller
{
    public function __construct(
        private FiscalWebhookCallbackService $callbackService,
    ) {}

    /**
     * POST /api/v1/fiscal/webhook
     * Body (exemplo): { "ref": "nfe_1_...", "status": "autorizado", "chave_nfe": "...", "numero": "123", ... }
     */
    public function __invoke(FiscalWebhookCallbackRequest $request): JsonResponse
    {
        $payload = $request->validated();
        if (empty($payload)) {
            $payload = $request->json()->all();
        }

        if (empty($payload)) {
            Log::warning('FiscalWebhookCallback: empty body');

            return ApiResponse::message('Payload vazio.', 422);
        }

        $result = $this->callbackService->process($payload);

        if (! $result['processed']) {
            return ApiResponse::data([
                'received' => true,
                'processed' => false,
                'message' => $result['message'],
            ], $result['note_id'] ? 200 : 404);
        }

        return ApiResponse::data([
            'received' => true,
            'processed' => true,
            'note_id' => $result['note_id'],
            'message' => $result['message'],
            'status' => $result['status'] ?? null,
        ]);
    }
}
