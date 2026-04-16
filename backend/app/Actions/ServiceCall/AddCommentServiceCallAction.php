<?php

namespace App\Actions\ServiceCall;

use App\Models\AuditLog;
use App\Models\ServiceCall;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AddCommentServiceCallAction extends BaseServiceCallAction
{
    /**
     * @param  array<string, mixed>  $data
     * @return mixed
     */
    public function execute(array $data, ServiceCall $serviceCall, User $user, int $tenantId)
    {

        if ((int) $serviceCall->tenant_id !== $tenantId) {
            return $this->notFoundResponse();
        }

        $validated = $data;

        try {
            $comment = DB::transaction(function () use ($serviceCall, $validated, $user) {
                $comment = $serviceCall->comments()->create([
                    'tenant_id' => $serviceCall->tenant_id,
                    'user_id' => $user->id,
                    'content' => $validated['content'],
                ]);

                AuditLog::log('commented', "Comentário adicionado ao chamado {$serviceCall->call_number}", $serviceCall);

                return $comment;
            });

            return ApiResponse::data($comment->load('user:id,name'), 201);
        } catch (\Throwable $e) {
            Log::error('ServiceCall comment failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao adicionar comentário', 500);
        }
    }
}
