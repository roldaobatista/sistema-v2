<?php

namespace App\Actions\Crm;

use App\Models\CrmActivity;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DeleteCrmActivityAction
{
    /**
     * @return mixed
     */
    public function execute(CrmActivity $activity, User $user, int $tenantId)
    {
        try {
            DB::transaction(fn () => $activity->delete());

            return ApiResponse::noContent();
        } catch (\Exception $e) {
            Log::error('Erro ao excluir atividade', ['id' => $activity->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao excluir atividade', 500);
        }
    }
}
