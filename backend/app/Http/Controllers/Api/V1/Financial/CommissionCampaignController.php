<?php

namespace App\Http\Controllers\Api\V1\Financial;

use App\Http\Controllers\Controller;
use App\Http\Requests\Financial\StoreCommissionCampaignRequest;
use App\Http\Requests\Financial\UpdateCommissionCampaignRequest;
use App\Http\Resources\CommissionCampaignResource;
use App\Models\CommissionCampaign;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CommissionCampaignController extends Controller
{
    use ResolvesCurrentTenant;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CommissionCampaign::class);

        $query = CommissionCampaign::where('tenant_id', $this->tenantId());

        if ($request->boolean('active_only') || $request->boolean('active')) {
            $query->active();
        }

        $paginator = $query->orderByDesc('created_at')->paginate(min((int) $request->input('per_page', 25), 100));

        return ApiResponse::paginated($paginator, [], [], CommissionCampaignResource::class);
    }

    public function store(StoreCommissionCampaignRequest $request): JsonResponse
    {
        $this->authorize('create', CommissionCampaign::class);

        $validated = $request->validated();

        try {
            $campaign = DB::transaction(function () use ($validated) {
                return CommissionCampaign::create([
                    ...$validated,
                    'tenant_id' => $this->tenantId(),
                    'active' => true,
                ]);
            });

            return ApiResponse::data(new CommissionCampaignResource($campaign), 201);
        } catch (\Throwable $e) {
            Log::error('Commission campaign store failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao criar campanha.', 500);
        }
    }

    public function update(UpdateCommissionCampaignRequest $request, int $id): JsonResponse
    {
        $campaign = CommissionCampaign::where('tenant_id', $this->tenantId())->find($id);

        if (! $campaign) {
            return ApiResponse::message('Campanha nao encontrada.', 404);
        }

        $this->authorize('update', $campaign);

        $validated = $request->validated();

        try {
            DB::transaction(function () use ($campaign, $validated) {
                $campaign->update($validated);
            });

            return ApiResponse::data(new CommissionCampaignResource($campaign->fresh()));
        } catch (\Throwable $e) {
            Log::error('Commission campaign update failed', [
                'campaign_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::message('Erro ao atualizar campanha.', 500);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        $campaign = CommissionCampaign::where('tenant_id', $this->tenantId())->find($id);

        if (! $campaign) {
            return ApiResponse::message('Campanha nao encontrada.', 404);
        }

        $this->authorize('delete', $campaign);

        try {
            DB::transaction(fn () => $campaign->delete());

            return ApiResponse::noContent();
        } catch (\Throwable $e) {
            Log::error('Commission campaign destroy failed', [
                'campaign_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::message('Erro ao excluir campanha.', 500);
        }
    }
}
