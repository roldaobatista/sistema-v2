<?php

namespace App\Actions\Crm;

use App\Models\CrmDeal;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BulkUpdateDealsAction
{
    /**
     * @param  array<string, mixed>  $data
     * @return mixed
     */
    public function execute(array $data, User $user, int $tenantId)
    {
        $dealIds = ($data['deal_ids'] ?? null);
        $action = ($data['action'] ?? null);

        $deals = CrmDeal::where('tenant_id', $tenantId)
            ->whereIn('id', $dealIds)
            ->get();

        if ($deals->isEmpty()) {
            return ApiResponse::message('Nenhum deal encontrado', 404);
        }

        try {
            DB::transaction(function () use ($deals, $action, $data) {
                foreach ($deals as $deal) {
                    switch ($action) {
                        case 'move_stage':
                            $deal->moveToStage(($data['stage_id'] ?? null));
                            break;
                        case 'mark_won':
                            $deal->markAsWon();
                            break;
                        case 'mark_lost':
                            $deal->markAsLost();
                            break;
                        case 'delete':
                            $deal->activities()->delete();
                            $deal->delete();
                            break;
                    }
                }
            });

            return ApiResponse::data([
                'message' => 'Operação em massa concluída',
                'affected' => $deals->count(),
            ]);
        } catch (\Exception $e) {
            Log::error('Erro bulk update deals', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao processar operação em massa', 500);
        }
    }
}
