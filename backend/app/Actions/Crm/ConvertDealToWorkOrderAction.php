<?php

namespace App\Actions\Crm;

use App\Models\CrmDeal;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ConvertDealToWorkOrderAction
{
    /**
     * @param  array<string, mixed>  $data
     * @return mixed
     */
    public function execute(array $data, CrmDeal $deal, User $user, int $tenantId)
    {
        if ($deal->tenant_id !== $tenantId) {
            return ApiResponse::message('Negócio não encontrado.', 404);
        }
        if (! $deal->customer_id) {
            return ApiResponse::message('O negócio precisa ter um cliente vinculado para criar a OS.', 422);
        }
        if ($deal->work_order_id) {
            return ApiResponse::data([
                'message' => 'Este negócio já possui uma OS vinculada.',
                'work_order_id' => $deal->work_order_id,
            ], 422);
        }

        try {
            $wo = DB::transaction(function () use ($deal, $user) {
                $wo = WorkOrder::create([
                    'tenant_id' => $deal->tenant_id,
                    'number' => WorkOrder::nextNumber($deal->tenant_id),
                    'customer_id' => $deal->customer_id,
                    'created_by' => $user->id,
                    'status' => WorkOrder::STATUS_OPEN,
                    'priority' => WorkOrder::PRIORITY_MEDIUM,
                    'description' => "Gerada a partir do negócio: {$deal->title}",
                    'total' => $deal->value ?? 0,
                    'origin_type' => WorkOrder::ORIGIN_MANUAL,
                    'lead_source' => $deal->source,
                    'seller_id' => $deal->assigned_to,
                ]);
                $deal->update(['work_order_id' => $wo->id]);

                return $wo->load('customer:id,name');
            });

            return ApiResponse::data(['work_order' => $wo, 'message' => 'OS criada com sucesso.'], 201);
        } catch (\Exception $e) {
            Log::error('Erro ao criar OS a partir do deal', ['deal_id' => $deal->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao criar OS. Tente novamente.', 500);
        }
    }
}
