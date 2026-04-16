<?php

namespace App\Actions\Crm;

use App\Http\Resources\CrmActivityResource;
use App\Models\CrmActivity;
use App\Models\Customer;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CreateCrmActivityAction
{
    /**
     * @param  array<string, mixed>  $data
     * @return mixed
     */
    public function execute(array $data, User $user, int $tenantId)
    {
        $data['tenant_id'] = $tenantId;
        $data['user_id'] = $user->id;

        DB::beginTransaction();

        try {
            $activity = CrmActivity::create($data);

            Customer::where('id', $data['customer_id'])
                ->update(['last_contact_at' => now()]);

            DB::commit();

            return ApiResponse::data(new CrmActivityResource($activity->load([
                'customer:id,name', 'deal:id,title', 'user:id,name', 'contact:id,name',
            ])), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erro ao criar atividade', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao criar atividade', 500);
        }
    }
}
