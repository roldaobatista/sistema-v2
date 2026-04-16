<?php

namespace App\Actions\Crm;

use App\Http\Resources\CrmDealResource;
use App\Models\CrmActivity;
use App\Models\CrmDeal;
use App\Models\Customer;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MarkDealAsWonAction
{
    /**
     * @param  array<string, mixed>  $data
     * @return mixed
     */
    public function execute(array $data, CrmDeal $deal, User $user, int $tenantId)
    {
        if ($deal->status === CrmDeal::STATUS_WON) {
            return ApiResponse::message('Deal já está marcado como ganho', 422);
        }

        DB::beginTransaction();

        try {
            $deal->markAsWon();

            CrmActivity::logSystemEvent(
                $deal->tenant_id,
                $deal->customer_id,
                "Deal ganho: {$deal->title} (R$ ".number_format((float) $deal->value, 2, ',', '.').')',
                $deal->id
            );

            Customer::where('id', $deal->customer_id)
                ->update(['last_contact_at' => now()]);

            DB::commit();

            return ApiResponse::data(new CrmDealResource($deal->fresh()->load([
                'customer:id,name', 'stage:id,name,color', 'pipeline:id,name',
            ])));
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erro ao marcar deal como ganho', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao marcar deal como ganho', 500);
        }
    }
}
