<?php

namespace App\Actions\ServiceCall;

use App\Models\ServiceCall;
use App\Models\User;
use App\Support\ApiResponse;

class CommentsServiceCallAction extends BaseServiceCallAction
{
    /**
     * @return mixed
     */
    public function execute(ServiceCall $serviceCall, User $user, int $tenantId)
    {

        if ((int) $serviceCall->tenant_id !== $tenantId) {
            return $this->notFoundResponse();
        }

        return ApiResponse::data($serviceCall->comments()->with('user:id,name')->get());
    }
}
