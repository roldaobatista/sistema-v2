<?php

namespace App\Actions\Crm;

use App\Http\Resources\CrmDealResource;
use App\Models\CrmActivity;
use App\Models\CrmDeal;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MarkDealAsLostAction
{
    /**
     * @param  array<string, mixed>  $data
     * @return mixed
     */
    public function execute(array $data, CrmDeal $deal, User $user, int $tenantId)
    {
        if ($deal->status === CrmDeal::STATUS_LOST) {
            return ApiResponse::message('Deal já está marcado como perdido', 422);
        }

        DB::beginTransaction();

        try {
            $deal->markAsLost($data['lost_reason'] ?? null);

            CrmActivity::logSystemEvent(
                $deal->tenant_id,
                $deal->customer_id,
                "Deal perdido: {$deal->title}".(! empty($data['lost_reason']) ? " — Motivo: {$data['lost_reason']}" : ''),
                $deal->id
            );

            DB::commit();

            return ApiResponse::data(new CrmDealResource($deal->fresh()->load([
                'customer:id,name', 'stage:id,name,color', 'pipeline:id,name',
            ])));
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erro ao marcar deal como perdido', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao marcar deal como perdido', 500);
        }
    }
}
