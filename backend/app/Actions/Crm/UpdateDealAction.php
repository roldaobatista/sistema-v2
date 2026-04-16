<?php

namespace App\Actions\Crm;

use App\Http\Resources\CrmDealResource;
use App\Models\CrmDeal;
use App\Models\User;
use App\Support\ApiResponse;

class UpdateDealAction
{
    /**
     * @param  array<string, mixed>  $data
     * @return mixed
     */
    public function execute(array $data, CrmDeal $deal, User $user, int $tenantId)
    {
        $deal->update($data);

        return ApiResponse::data(new CrmDealResource($deal->load([
            'customer:id,name', 'stage:id,name,color', 'pipeline:id,name',
        ])));
    }
}
