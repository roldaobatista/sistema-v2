<?php

namespace App\Actions\Crm;

use App\Http\Resources\CrmActivityResource;
use App\Models\CrmActivity;
use App\Models\Customer;
use App\Models\User;
use App\Support\ApiResponse;

class UpdateCrmActivityAction
{
    /**
     * @param  array<string, mixed>  $data
     * @return mixed
     */
    public function execute(array $data, CrmActivity $activity, User $user, int $tenantId)
    {
        $activity->update($data);

        if (isset($data['completed_at'])) {
            Customer::where('id', $activity->customer_id)
                ->update(['last_contact_at' => now()]);
        }

        return ApiResponse::data(new CrmActivityResource($activity->load([
            'customer:id,name', 'deal:id,title', 'user:id,name',
        ])));
    }
}
