<?php

namespace App\Actions\Crm;

use App\Http\Resources\CrmDealResource;
use App\Models\CrmActivity;
use App\Models\CrmDeal;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdateDealStageAction
{
    /**
     * @param  array<string, mixed>  $data
     * @return mixed
     */
    public function execute(array $data, CrmDeal $deal, User $user, int $tenantId)
    {
        DB::beginTransaction();

        try {
            $deal->moveToStage($data['stage_id']);

            CrmActivity::logSystemEvent(
                $deal->tenant_id,
                $deal->customer_id,
                'Deal movido para estágio: '.$deal->fresh()->stage->name,
                $deal->id
            );

            DB::commit();

            return ApiResponse::data(new CrmDealResource($deal->fresh()->load([
                'customer:id,name', 'stage:id,name,color,sort_order', 'pipeline:id,name',
            ])));
        } catch (\DomainException $e) {
            DB::rollBack();

            return ApiResponse::message($e->getMessage(), 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erro ao mover deal de estágio', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao mover deal de estágio', 500);
        }
    }
}
