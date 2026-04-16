<?php

namespace App\Http\Controllers\Api\V1\Analytics;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ai\AiChatRequest;
use App\Services\AiAssistantService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class AiAssistantController extends Controller
{
    /**
     * POST /api/v1/ai/chat — Process a natural language query.
     */
    public function chat(AiChatRequest $request, AiAssistantService $assistant): JsonResponse
    {
        $tenantId = (int) app('current_tenant_id');

        $response = $assistant->chat(
            $request->validated()['message'],
            $tenantId
        );

        return ApiResponse::data($response, 200, $response);
    }

    /**
     * GET /api/v1/ai/tools — List available AI tools.
     */
    public function tools(AiAssistantService $assistant): JsonResponse
    {
        $tools = $assistant->listTools();

        return ApiResponse::data(['tools' => $tools], 200, ['tools' => $tools]);
    }
}
