<?php

namespace App\Actions\ServiceCall;

use App\Http\Resources\ServiceCallResource;
use App\Models\ServiceCall;
use App\Models\User;
use App\Support\ApiResponse;

class ShowServiceCallAction extends BaseServiceCallAction
{
    /**
     * @return mixed
     */
    public function execute(ServiceCall $serviceCall, User $user, int $tenantId)
    {

        if ((int) $serviceCall->tenant_id !== $tenantId) {
            return $this->notFoundResponse();
        }

        return ApiResponse::data(new ServiceCallResource($serviceCall->load([
            'customer.contacts', 'quote', 'technician:id,name', 'driver:id,name',
            'createdBy:id,name', 'equipments', 'comments.user:id,name',
            'workOrders:id,service_call_id,number,os_number,status,created_at',
        ])));
    }
}
