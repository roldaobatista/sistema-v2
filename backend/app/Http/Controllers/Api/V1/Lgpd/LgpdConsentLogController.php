<?php

namespace App\Http\Controllers\Api\V1\Lgpd;

use App\Http\Controllers\Controller;
use App\Http\Requests\Lgpd\RevokeLgpdConsentLogRequest;
use App\Http\Requests\Lgpd\StoreLgpdConsentLogRequest;
use App\Http\Resources\LgpdConsentLogResource;
use App\Models\LgpdConsentLog;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LgpdConsentLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $consents = LgpdConsentLog::query()
            ->when($request->input('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->input('holder_document'), fn ($q, $v) => $q->where('holder_document', $v))
            ->when($request->input('purpose'), fn ($q, $v) => $q->where('purpose', $v))
            ->orderByDesc('created_at')
            ->paginate(max(1, min($request->integer('per_page', 15), 100)));

        return ApiResponse::paginated($consents, resourceClass: LgpdConsentLogResource::class);
    }

    public function store(StoreLgpdConsentLogRequest $request): JsonResponse
    {
        $consent = LgpdConsentLog::create([
            ...$request->validated(),
            'tenant_id' => $request->user()->current_tenant_id,
            'status' => 'granted',
            'granted_at' => now(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return ApiResponse::data(new LgpdConsentLogResource($consent), 201);
    }

    public function show(int $id): JsonResponse
    {
        $consent = LgpdConsentLog::findOrFail($id);

        return ApiResponse::data(new LgpdConsentLogResource($consent));
    }

    public function revoke(RevokeLgpdConsentLogRequest $request, int $id): JsonResponse
    {
        $consent = LgpdConsentLog::findOrFail($id);

        if ($consent->status === 'revoked') {
            return ApiResponse::message('Consentimento já revogado.', 422);
        }

        $consent->revoke($request->validated('reason'));

        return ApiResponse::data(new LgpdConsentLogResource($consent));
    }
}
