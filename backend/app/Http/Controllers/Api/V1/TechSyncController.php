<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\TechSync\TechSyncBatchPushRequest;
use App\Http\Requests\TechSync\TechSyncPullRequest;
use App\Http\Requests\TechSync\TechSyncUploadPhotoRequest;
use App\Models\Equipment;
use App\Models\Expense;
use App\Services\TechSyncService;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;

/**
 * Tech Sync API (PWA / mobile offline).
 *
 * Autenticação: Bearer token (mesmo da API). Todas as requisições exigem auth.
 *
 * Pull: GET /api/v1/tech/sync?since=ISO8601
 *   - work_orders, equipment, checklists, standard_weights atualizados desde @since.
 *   - Resposta inclui equipment_ids e assigned_tos por OS.
 *
 * Push: POST /api/v1/tech/sync/batch { mutations: [{ type, data }] }
 *   - Tipos: checklist_response, expense, signature, status_change, displacement_*.
 *   - Conflito: se a OS foi alterada no servidor após o client_work_order_updated_at
 *     (enviado pelo cliente em checklist_response) ou em status_change, o servidor
 *     devolve o item em conflicts e não aplica (evita sobreposição).
 *
 * Compatível com cliente IndexedDB (PWA) ou futuro app WatermelonDB (React Native).
 */
class TechSyncController extends Controller
{
    use ResolvesCurrentTenant;

    public function __construct(private TechSyncService $service) {}

    /**
     * Pull updated data for the authenticated technician.
     * GET /api/tech/sync?since=ISO_TIMESTAMP
     */
    public function pull(TechSyncPullRequest $request): JsonResponse
    {
        $this->service->setContext($this->tenantId(), $request->user());

        return $this->service->pull($request->validated());
    }

    /**
     * Receive batch mutations from offline technician.
     * POST /api/tech/sync/batch
     */
    public function batchPush(TechSyncBatchPushRequest $request): JsonResponse
    {
        $this->service->setContext($this->tenantId(), $request->user());

        return $this->service->batchPush($request->sanitizedPayload());
    }

    /**
     * Receive a single photo upload.
     * POST /api/tech/sync/photo
     */
    public function uploadPhoto(TechSyncUploadPhotoRequest $request): JsonResponse
    {
        $this->service->setContext($this->tenantId(), $request->user());

        return $this->service->uploadPhoto($request->validated());
    }
}
