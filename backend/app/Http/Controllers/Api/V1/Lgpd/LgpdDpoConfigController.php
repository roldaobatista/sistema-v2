<?php

namespace App\Http\Controllers\Api\V1\Lgpd;

use App\Http\Controllers\Controller;
use App\Http\Requests\Lgpd\StoreLgpdDpoConfigRequest;
use App\Http\Resources\LgpdDpoConfigResource;
use App\Models\LgpdDpoConfig;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LgpdDpoConfigController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $config = LgpdDpoConfig::where('tenant_id', $request->user()->current_tenant_id)->first();

        if (! $config) {
            return ApiResponse::message('DPO não configurado.', 404);
        }

        return ApiResponse::data(new LgpdDpoConfigResource($config));
    }

    public function upsert(StoreLgpdDpoConfigRequest $request): JsonResponse
    {
        $config = LgpdDpoConfig::updateOrCreate(
            ['tenant_id' => $request->user()->current_tenant_id],
            [
                ...$request->validated(),
                'updated_by' => $request->user()->id,
            ]
        );

        return ApiResponse::data(new LgpdDpoConfigResource($config));
    }
}
