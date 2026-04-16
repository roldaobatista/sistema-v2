<?php

namespace App\Actions\Crm;

use App\Http\Resources\CrmDealResource;
use App\Models\CrmDeal;
use App\Models\Customer;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CreateDealAction
{
    /**
     * @param  array<string, mixed>  $data
     * @return mixed
     */
    public function execute(array $data, User $user, int $tenantId)
    {
        $data['tenant_id'] = $tenantId;
        $data['status'] = CrmDeal::STATUS_OPEN;

        DB::beginTransaction();

        try {
            $deal = CrmDeal::create($data);

            Customer::where('id', $data['customer_id'])
                ->update(['last_contact_at' => now()]);

            DB::commit();

            return ApiResponse::data(new CrmDealResource($deal->load([
                'customer:id,name', 'stage:id,name,color', 'pipeline:id,name',
            ])), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erro ao criar deal', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao criar deal', 500);
        }
    }
}
