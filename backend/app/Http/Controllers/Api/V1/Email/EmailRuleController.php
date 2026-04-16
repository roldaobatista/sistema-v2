<?php

namespace App\Http\Controllers\Api\V1\Email;

use App\Http\Controllers\Controller;
use App\Http\Requests\Email\StoreEmailRuleRequest;
use App\Http\Requests\Email\UpdateEmailRuleRequest;
use App\Models\EmailRule;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EmailRuleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $rules = EmailRule::where('tenant_id', $request->user()->current_tenant_id)
            ->orderBy('priority')
            ->orderBy('name')
            ->paginate(min((int) request()->input('per_page', 25), 100));

        return ApiResponse::data($rules);
    }

    public function show(Request $request, EmailRule $emailRule): JsonResponse
    {
        $this->authorizeTenant($request, $emailRule);

        return ApiResponse::data($emailRule);
    }

    public function store(StoreEmailRuleRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $rule = EmailRule::create(array_merge(
                $request->validated(),
                ['tenant_id' => $request->user()->current_tenant_id]
            ));

            DB::commit();

            return ApiResponse::data($rule, 201, ['message' => 'Regra de email criada com sucesso']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Email rule creation failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao criar regra', 500);
        }
    }

    public function update(UpdateEmailRuleRequest $request, EmailRule $emailRule): JsonResponse
    {
        $this->authorizeTenant($request, $emailRule);

        try {
            DB::beginTransaction();
            $emailRule->update($request->validated());
            DB::commit();

            return ApiResponse::data($emailRule->fresh(), 200, ['message' => 'Regra atualizada com sucesso']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Email rule update failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao atualizar regra', 500);
        }
    }

    public function destroy(Request $request, EmailRule $emailRule): JsonResponse
    {
        $this->authorizeTenant($request, $emailRule);

        $emailRule->delete();

        return ApiResponse::message('Regra removida com sucesso');
    }

    public function toggleActive(Request $request, EmailRule $emailRule): JsonResponse
    {
        $this->authorizeTenant($request, $emailRule);

        $emailRule->update(['is_active' => ! $emailRule->is_active]);

        return ApiResponse::data($emailRule->fresh(), 200, [
            'message' => $emailRule->is_active ? 'Regra ativada' : 'Regra desativada',
        ]);
    }

    private function authorizeTenant(Request $request, EmailRule $emailRule): void
    {
        abort_if(
            $emailRule->tenant_id !== $request->user()->current_tenant_id,
            403,
            'Acesso negado'
        );
    }
}
